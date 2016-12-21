<?php

namespace Csm\Tests\Driver;

use Csm\Containers\ChainElement;
use Csm\CsmException;
use Csm\Driver\GoogleCloud;
use Csm\Tests\Stub\Helper;
use Csm\Tests\Stub\Stub2;

class GoogleCloudTest extends \PHPUnit_Framework_TestCase
{
    use Helper;

    /**
     * @var array
     */
    protected $chainElements;

    /** @test */
    public function testBasic()
    {
        $fullFilename = $this->getFullPath() . '/' . $this->getFilename();
        $driver = new GoogleCloud($this->getParams());
        $driverNs = new GoogleCloud($this->getParams(['strict' => false]));

        // file and url is absent berfore creating
        $this->assertFalse($driver->isPresent($this->getIdent(), $this->getFilename()));
        $this->assertFalse($driver->getPreparedUrl($this->getIdent(), $this->getFilename()));

        // saving file
        $this->assertTrue($driver->set($this->getIdent(), $this->getContent(), $this->getFilename()));

        // present file?
        $this->assertTrue($driver->isPresent($this->getIdent(), $this->getFilename()));

        // try to get content
        $this->assertEquals($this->getContent(), $driver->get($this->getIdent(), $this->getFilename()));

        // get url (strict not strict)
        $this->assertContains(
            $this->getUrl($this->getFilename()),
            $driver->getPreparedUrl($this->getIdent(), $this->getFilename())
        );
        $this->assertFalse($driver->getPreparedUrl($this->getIdent(), '2.txt'));
        /*$this->assertEquals(
            $this->getUrl('2.txt'),
            $driverNs->getPreparedUrl($this->getIdent(), '2.txt')
        );*/

        // copy file (to same and other Ident)
        $this->assertTrue($driver->copy($this->getIdent(), $this->getIdent(), $this->getFilename(), '2.txt'));
        $this->assertEquals($this->getContent(), $driver->get($this->getIdent(), '2.txt'));
        $this->assertTrue($driver->copy($this->getIdent(), $this->getIdent('next'), '2.txt'));
        $this->assertTrue($driver->copy($this->getIdent('next'), $this->getIdent('nextNext'), '2.txt', '3.txt'));
        $this->assertTrue($driver->isPresent($this->getIdent('nextNext'), '3.txt'));
        $this->assertEquals($this->getContent(), $driver->get($this->getIdent('nextNext'), '3.txt'));

        // deleting file
        $this->assertTrue($driver->delete($this->getIdent(), $this->getFilename()));
        $this->assertTrue($driver->delete($this->getIdent(), '2.txt'));
        $this->assertTrue($driver->delete($this->getIdent('next'), '2.txt'));
        $this->assertTrue($driver->delete($this->getIdent('nextNext'), '3.txt'));
        // check after deleting
        $this->assertFalse($driver->isPresent($this->getIdent(), $this->getFilename()));
        $this->assertFalse($driver->getPreparedUrl($this->getIdent(), $this->getFilename()));
        // cahceable
        //$this->assertFalse($driverNs->get($this->getIdent(), $this->getFilename()));
    }

    /** @test */
    public function testCanNotReadNotExistingFile()
    {
        // not strict
        $driver = new GoogleCloud(array_merge($this->getParams(), ['strict' => false]));
        $this->assertFalse($driver->get($this->getIdent(), 'file.txt'));

        // strict
        $driver = new GoogleCloud($this->getParams());
        $this->expectException(CsmException::class);
        $this->expectExceptionMessageRegExp('/Can not read file/');
        $this->assertFalse($driver->get($this->getIdent(), 'file.txt'));
    }

    /** @test  */
    public function testCanNotSaveErrors()
    {
        // not strict
        $driver = new Stub2($this->getParams(['strict' => false]));
        $this->assertFalse($driver->set($this->getIdent(), 'as', 'as'));

        // strict
        $driver = new Stub2($this->getParams());
        $this->expectException(CsmException::class);
        $this->expectExceptionMessageRegExp('/Can not save file/');
        $this->assertFalse($driver->set($this->getIdent(), 'as', 'as'));
    }

    /** @test  */
    public function testDeleteErrors()
    {
        // not strict
        $driver = new GoogleCloud($this->getParams(['strict' => false]));
        $this->assertFalse($driver->delete($this->getIdent(), 'as'));

        // strict
        $driver = new GoogleCloud($this->getParams());
        $this->expectException(CsmException::class);
        $this->expectExceptionMessageRegExp('/Can not delete file/');
        $this->assertFalse($driver->delete($this->getIdent(), 'as'));
    }

    /** @test  */
    public function testCopyErrors()
    {
        // not strict
        $driver = new GoogleCloud($this->getParams(['strict' => false]));
        $this->assertFalse($driver->copy($this->getIdent(), $this->getIdent(), 'as'));

        // strict
        $driver = new GoogleCloud($this->getParams());
        $this->expectException(CsmException::class);
        $this->expectExceptionMessageRegExp('/Can not copy file/');
        $this->assertFalse($driver->copy($this->getIdent(), $this->getIdent(), 'as'));
    }

    /** @test */
    public function testEvents()
    {
        $content = $this->getContentLong();
        $driver = new GoogleCloud($this->getParams([
            //'strict' => false,
            'readEvent' => function (ChainElement $chainElement) {
                $this->chainElements[] = $chainElement->toArray();
                return true;
            },
            'writeEvent' => function (ChainElement $chainElement) {
                $this->chainElements[] = $chainElement->toArray();
                return true;
            },
        ]));

        // saving file
        $this->chainElements = [];
        $this->assertTrue($driver->set($this->getIdent(), $content, '4'));
        $this->assertEquals([
            ['size' => 4723052, 'bytesProceed' => 0, 'completed' => 0],
            ['size' => 4723052, 'bytesProceed' => 4723052, 'completed' => 1]
        ], $this->chainElements);

        // try to get content
        $this->chainElements = [];
        $this->assertEquals($content, $driver->get($this->getIdent(), '4'));
        $this->assertEquals([
            ['size' => 4723052, 'bytesProceed' => 0, 'completed' => 0],
            ['size' => 4723052, 'bytesProceed' => 4723052, 'completed' => 1]
        ], $this->chainElements);

        // try small chain size
        $driver->setParam('chainSize', 1000000);
        $driver->setParam('readEvent', function (ChainElement $chainElement) {
            $this->chainElements[] = (string)$chainElement;
            return true;
        });
        $driver->setParam('writeEvent', function (ChainElement $chainElement) {
            $this->chainElements[] = (string)$chainElement;
            return true;
        });
        $this->chainElements = [];
        $this->assertTrue($driver->set($this->getIdent(), $content, '5'));
        $this->assertEquals(
            ['4723052_0_0', '4723052_1048576_0.22201237674283',
                '4723052_2097152_0.44402475348567', '4723052_3145728_0.6660371302285',
                '4723052_4194304_0.88804950697134', '4723052_4723052_1'],
            $this->chainElements
        );
        $this->chainElements = [];
        $this->assertEquals($content, $driver->get($this->getIdent(), '5'));
        $this->assertEquals(
            ['4723052_0_0', '4723052_1000000_0.21172750162395', '4723052_2000000_0.4234550032479',
                '4723052_3000000_0.63518250487185', '4723052_4000000_0.8469100064958',
                '4723052_4723052_1'],
            $this->chainElements
        );

        // try break reading by event
        $driver->setParam('strict', false);
        // when start
        $driver->setParam('readEvent', function (ChainElement $chainElement) {
            $this->chainElements[] = (string)$chainElement;
            return false;
        });
        $this->chainElements = [];
        $this->assertFalse($driver->get($this->getIdent(), '4'));
        $this->assertEquals(
            ['4723052_0_0'],
            $this->chainElements
        );
        // when works
        $driver->setParam('readEvent', function (ChainElement $chainElement) {
            $this->chainElements[] = (string)$chainElement;
            return $chainElement->completed > 0.6 ? false : true;
        });
        $this->chainElements = [];
        $this->assertFalse($driver->get($this->getIdent(), '4'));
        $this->assertEquals(
            ['4723052_0_0', '4723052_1000000_0.21172750162395', '4723052_2000000_0.4234550032479',
                '4723052_3000000_0.63518250487185'],
            $this->chainElements
        );

        // try break writing by event
        // when start
        $driver->setParam('writeEvent', function (ChainElement $chainElement) {
            $this->chainElements[] = (string)$chainElement;
            return false;
        });
        $this->chainElements = [];
        $this->assertFalse($driver->set($this->getIdent(), $content, '6'));
        $this->assertEquals(
            ['4723052_0_0'],
            $this->chainElements
        );
        $this->assertFalse($driver->isPresent($this->getIdent(), '6'));
        // when works
        $driver->setParam('writeEvent', function (ChainElement $chainElement) {
            $this->chainElements[] = (string)$chainElement;
            return $chainElement->completed > 0.6 ? false : true;
        });
        $this->chainElements = [];
        $this->assertFalse($driver->set($this->getIdent(), $content, '6'));
        $this->assertEquals(
            ['4723052_0_0', '4723052_1048576_0.22201237674283', '4723052_2097152_0.44402475348567',
                '4723052_3145728_0.6660371302285'],
            $this->chainElements
        );
        $this->assertFalse($driver->isPresent($this->getIdent(), '6'));

        // clear
        $driver->delete($this->getIdent(), '4');
        $driver->delete($this->getIdent(), '5');
        $driver->delete($this->getIdent(), '6');
    }

    /**
     * @return void
     */
    protected function setUp()
    {
        // nothing
    }

    protected function tearDown()
    {
        // nothing
    }

    /**
     * @param array $moreParams
     * @return array
     */
    protected function getParams(array $moreParams = array())
    {
        return array_merge([
            'credentialsPath' => __DIR__.'/../resources/google-credentials.json',
            'projectId' => 'myProjectId',
            'bucket' => 'mybucket',
            'resourcePath' => 'oitimon/phpunit',
            //'resourceUrl'  => '/test',
        ], $moreParams);
    }

    /**
     * @return string
     */
    protected function getContentLong()
    {
        return file_get_contents(__DIR__ . '/../resources/pexels-photo.jpg');
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function getUrl($filename)
    {
        return 'https://www.googleapis.com/download/storage/v1/b/mybucket/o/oitimon-phpunit-users-Smith-'
            .'1e50210a0202497fb79bc38b6ade6c34-18990-' .$filename;
    }
}
