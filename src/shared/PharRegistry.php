<?php declare(strict_types=1);
/*
 * This file is part of Phive.
 *
 * Copyright (c) Arne Blankerts <arne@blankerts.de>, Sebastian Heuer <sebastian@phpeople.de> and contributors
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */
namespace PharIo\Phive;

use function array_unique;
use function chmod;
use function file_get_contents;
use function in_array;
use function sprintf;
use DOMElement;
use DOMNode;
use DOMNodeList;
use PharIo\FileSystem\Directory;
use PharIo\FileSystem\DirectoryException;
use PharIo\FileSystem\File;
use PharIo\FileSystem\Filename;
use PharIo\Version\Version;

class PharRegistry {
    /** @var Directory */
    private $pharDirectory;

    /** @var XmlFile */
    private $dbFile;

    public function __construct(XmlFile $xmlFile, Directory $pharDirectory) {
        $this->dbFile = $xmlFile;

        $pharDirectory->ensureExists(0755);
        $this->pharDirectory = $pharDirectory;
    }

    public function addPhar(Phar $phar): Phar {
        $destinationFile = $this->savePhar($phar);
        $pharNode        = $this->dbFile->createElement('phar');
        $pharNode->setAttribute('name', $phar->getName());
        $pharNode->setAttribute('version', $phar->getVersion()->getVersionString());
        $pharNode->setAttribute('location', $destinationFile->asString());
        $hashNode = $this->dbFile->createElement('hash', Sha1Hash::forContent($phar->getFile()->getContent())->asString());
        $hashNode->setAttribute('type', 'sha1');
        $pharNode->appendChild($hashNode);

        if ($phar->hasSignatureFingerprint()) {
            $signatureNode = $this->dbFile->createElement('signature');
            $signatureNode->setAttribute('fingerprint', $phar->getSignatureFingerprint());
            $pharNode->appendChild($signatureNode);
        }

        $this->dbFile->addElement($pharNode);
        $this->dbFile->save();

        return new Phar(
            $phar->getName(),
            $phar->getVersion(),
            new File($destinationFile, $phar->getFile()->getContent()),
            $phar->hasSignatureFingerprint() ? $phar->getSignatureFingerprint() : null
        );
    }

    /**
     * @param string $name
     *
     * @return Phar[]
     */
    public function getPhars($name): array {
        $phars = [];

        foreach ($this->dbFile->query(sprintf('//phive:phar[@name="%s"]', $name)) as $pharNode) {
            /** @var DOMElement $pharNode */
            $phars[] = $this->nodeToPhar($pharNode);
        }

        return $phars;
    }

    public function addUsage(Phar $phar, Filename $destination): void {
        $absolutePath = $destination->withAbsolutePath()->asString();

        $oldUsage = $this->dbFile->query(sprintf('//phive:usage[@destination="%s"]', $absolutePath))->item(0);

        if ($oldUsage instanceof DOMNodeList) {
            $oldUsage->parentNode->removeChild($oldUsage);
        }

        $usageNode = $this->dbFile->createElement('usage');
        $usageNode->setAttribute('destination', $absolutePath);

        $pharNode = $this->getFirstMatchingPharNode($phar->getName(), $phar->getVersion());

        if ($pharNode === null) {
            throw new PharRegistryException('No phar node for specified name and version found');
        }
        $pharNode->appendChild($usageNode);
        $this->dbFile->save();
    }

    /**
     * @throws PharRegistryException
     */
    public function getPhar(string $name, Version $version): Phar {
        if (!$this->hasPhar($name, $version)) {
            throw new PharRegistryException(sprintf(
                'Phar %s %s not found in database.',
                $name,
                $version->getVersionString()
            ));
        }

        return $this->nodeToPhar($this->getFirstMatchingPharNode($name, $version));
    }

    public function hasPhar(string $name, Version $version): bool {
        return $this->getFirstMatchingPharNode($name, $version) !== null;
    }

    /**
     * @throws PharRegistryException
     */
    public function getByUsage(Filename $filename): Phar {
        $pharNode = $this->dbFile->query(
            sprintf('//phive:phar[phive:usage/@destination="%s"]', $filename->asString())
        )->item(0);

        if ($pharNode instanceof DOMElement) {
            return $this->nodeToPhar($pharNode);
        }

        throw new PharRegistryException(
            sprintf('No phar with usage %s found', $filename->asString())
        );
    }

    public function removeUsage(Phar $phar, Filename $destination): void {
        $pharNode  = $this->getFirstMatchingPharNode($phar->getName(), $phar->getVersion());
        $usageNode = $this->dbFile->query(
            sprintf('//phive:usage[@destination="%s"]', $destination->asString()),
            $pharNode
        )->item(0);

        if ($usageNode === null) {
            throw new PharRegistryException(
                sprintf('No phar with usage %s found', $destination->asString())
            );
        }

        $pharNode->removeChild($usageNode);
        $this->dbFile->save();
    }

    public function removePhar(Phar $phar): void {
        $pharNode = $this->getFirstMatchingPharNode($phar->getName(), $phar->getVersion());
        $phar->getFile()->getFilename()->delete();
        $pharNode->parentNode->removeChild($pharNode);
        $this->dbFile->save();
    }

    public function hasUsages(Phar $phar): bool {
        $pharNode = $this->getFirstMatchingPharNode($phar->getName(), $phar->getVersion());

        return $this->dbFile->query('phive:usage', $pharNode)->length > 0;
    }

    /**
     * @return Phar[]
     */
    public function getUnusedPhars(): array {
        $unusedPhars = [];

        foreach ($this->dbFile->query('//phive:phar[not(phive:usage)]') as $pharNode) {
            /** @var DOMElement $pharNode */
            $unusedPhars[] = $this->nodeToPhar($pharNode);
        }

        return $unusedPhars;
    }

    /**
     * @return UsedPhar[]
     */
    public function getAllPhars(): array {
        $installedPhars = [];

        foreach ($this->dbFile->query('//phive:phar') as $pharNode) {
            /** @var DOMElement $pharNode */
            $installedPhars[] = $this->nodetoUsedPhar($pharNode);
        }

        return $installedPhars;
    }

    /**
     * @return Phar[]
     */
    public function getUsedPharsByDestination(Directory $destination): array {
        $usedPhars = [];
        $query     = sprintf('//phive:phar[contains(phive:usage/@destination, "%s")]', $destination->asString());

        foreach ($this->dbFile->query($query) as $pharNode) {
            /** @var DOMElement $pharNode */
            $usedPhars[] = $this->nodeToPhar($pharNode);
        }

        return $usedPhars;
    }

    public function getKnownSignatureFingerprints(string $alias): array {
        $fingerprints = [];
        $query        = sprintf('//phive:phar[@name="%s"]/phive:signature/@fingerprint', $alias);

        foreach ($this->dbFile->query($query) as $fingerprintNode) {
            if (in_array($fingerprintNode->nodeValue, $fingerprints, true)) {
                continue;
            }
            $fingerprints[] = $fingerprintNode->nodeValue;
        }

        return array_unique($fingerprints);
    }

    private function savePhar(Phar $phar): Filename {
        $destination = new Filename($this->getPharDestination($phar));

        $targetDir = $destination->getDirectory();

        try {
            $targetDir->ensureExists();
        } catch (DirectoryException $e) {
            throw new FileNotWritableException(
                sprintf(
                    "Cannot write phar to %s:\n%s",
                    $targetDir->asString(),
                    $e->getMessage()
                )
            );
        }

        if (!$targetDir->isWritable()) {
            throw new FileNotWritableException(
                sprintf('Cannot write phar to %s', $targetDir->asString())
            );
        }

        $phar->getFile()->saveAs($destination);
        chmod($destination->asString(), 0755);

        return $destination;
    }

    private function getPharDestination(Phar $phar): string {
        return sprintf(
            '%s/%s-%s.phar',
            $this->pharDirectory->asString(),
            $phar->getName(),
            $phar->getVersion()->getVersionString()
        );
    }

    /**
     * @psalm-ignore-nullable-return
     */
    private function getFirstMatchingPharNode(string $name, Version $version): ?DOMElement {
        $query    = sprintf('//phive:phar[@name="%s" and @version="%s"]', $name, $version->getVersionString());
        $pharNode = $this->dbFile->query($query)->item(0);

        assert($pharNode === null || $pharNode instanceof DOMElement);

        return $pharNode;
    }

    private function nodeToPhar(DOMElement $pharNode): Phar {
        return new Phar(
            $pharNode->getAttribute('name'),
            new Version($pharNode->getAttribute('version')),
            $this->loadPharFile($pharNode->getAttribute('location'))
        );
    }

    private function nodetoUsedPhar(DOMElement $pharNode): UsedPhar {
        $nodes = $this->dbFile->query('phive:usage', $pharNode);
        $path  = [];

        foreach ($nodes as $node) {
            /** @var DOMElement $node */
            $path[] = $node->getAttribute('destination');
        }

        return new UsedPhar(
            $pharNode->getAttribute('name'),
            new Version($pharNode->getAttribute('version')),
            $this->loadPharFile($pharNode->getAttribute('location')),
            $path
        );
    }

    private function loadPharFile(string $filename): File {
        return new File(new Filename($filename), file_get_contents($filename));
    }
}
