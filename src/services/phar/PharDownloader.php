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

use function sprintf;
use PharIo\FileSystem\File;

class PharDownloader {
    /** @var SignatureVerifier */
    private $signatureVerifier;

    /** @var ChecksumService */
    private $checksumService;

    /** @var PharRegistry */
    private $pharRegistry;

    /** @var HttpClient */
    private $httpClient;

    public function __construct(
        HttpClient $httpClient,
        SignatureVerifier $signatureVerifier,
        ChecksumService $checksumService,
        PharRegistry $pharRegistry
    ) {
        $this->signatureVerifier = $signatureVerifier;
        $this->checksumService   = $checksumService;
        $this->pharRegistry      = $pharRegistry;
        $this->httpClient        = $httpClient;
    }

    /**
     * @throws DownloadFailedException
     * @throws InvalidHashException
     */
    public function download(SupportedRelease $release, bool $skipSignatureVerification = false): Phar {
        $pharFile = $this->downloadFile($release->getUrl());

        $fingerprint = null;

        if (!$skipSignatureVerification && $release->hasSignatureUrl()) {
            $fingerprint = $this->verifySignature(
                $release,
                $pharFile,
                $this->pharRegistry->getKnownSignatureFingerprints($release->getName())
            );
        }

        return new Phar($release->getName(), $release->getVersion(), $pharFile, $fingerprint);
    }

    /**
     * @throws DownloadFailedException
     */
    private function downloadFile(Url $url): File {
        try {
            $response = $this->httpClient->get($url);

            if (!$response->isSuccess()) {
                throw new DownloadFailedException(
                    sprintf('Failed to download load %s: HTTP Code %d', $url->asString(), $response->getHttpCode()),
                    $response->getHttpCode()
                );
            }

            return new File($url->getFilename(), $response->getBody());
        } catch (HttpException $e) {
            throw new DownloadFailedException(
                sprintf('Unexpected HTTP error when requesting %s: %s', $url->asString(), $e->getMessage()),
                (int)$e->getCode(),
                $e
            );
        }
    }

    /**
     * @throws VerificationFailedException
     */
    private function verifySignature(SupportedRelease $release, File $phar, array $knownFingerprints): string {
        if (!$release->hasSignatureUrl()) {
            return '{NONE}';
        }

        /** @psalm-suppress PossiblyNullArgument */
        $signatureFile               = $this->downloadFile($release->getSignatureUrl());
        $signatureVerificationResult = $this->signatureVerifier->verify($phar->getContent(), $signatureFile->getContent(), $knownFingerprints);

        if (!$signatureVerificationResult->wasVerificationSuccessful()) {
            throw new VerificationFailedException(
                sprintf(
                    "Signature could not be verified\n%s",
                    $signatureVerificationResult->getErrorMessage()
                )
            );
        }

        $hash = $release->getExpectedHash();

        if ($hash !== null && !$this->checksumService->verify($hash, $phar)) {
            throw new VerificationFailedException(sprintf('Wrong checksum! Expected %s', $hash->asString()));
        }

        return $signatureVerificationResult->getFingerprint();
    }
}
