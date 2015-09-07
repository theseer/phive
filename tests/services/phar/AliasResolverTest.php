<?php
namespace PharIo\Phive {

    use Prophecy\Prophecy\ObjectProphecy;

    class AliasResolverTest extends \PHPUnit_Framework_TestCase {

        /**
         * @var PharIoRepositoryList|ObjectProphecy
         */
        private $repositoryList;

        protected function setUp() {
            $this->repositoryList = $this->prophesize(PharIoRepositoryList::class);
        }

        /**
         * @expectedException \PharIo\Phive\ResolveException
         */
        public function testThrowsExceptionIfListReturnsEmptyArray()
        {
            $alias = new PharAlias('phpunit');
            $this->repositoryList->getRepositoryUrls($alias)
                ->shouldBeCalled()
                ->willReturn([]);

            $resolver = new AliasResolver($this->repositoryList->reveal());
            $resolver->resolve($alias);
        }

        public function testReturnsExpectedArrayOfUrls()
        {
            $alias = new PharAlias('phpunit');

            $urls = [
                new Url('https://example.com/foo'),
                new Url('https://example.com/bar'),
            ];

            $this->repositoryList->getRepositoryUrls($alias)
                ->shouldBeCalled()
                ->willReturn($urls);

            $resolver = new AliasResolver($this->repositoryList->reveal());
            $this->assertEquals($urls, $resolver->resolve($alias));
        }

    }

}

