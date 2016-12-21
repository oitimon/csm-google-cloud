<?php

namespace Csm\Tests\Stub;

use Csm\CsmIdent;

trait Helper
{
    /**
     * @param array $moreParams
     * @return array
     */
    protected function getParams(array $moreParams = array())
    {
        return array_merge([
            'resourcePath' => $this->getReourceDirPath(true),
            'resourceUrl'  => 'http://localhost/test',
        ], $moreParams);
    }

    /**
     * @return string
     */
    protected function getContent()
    {
        return 'super content inside';
    }

    /**
     * @return string
     */
    protected function getContentLong()
    {
        return file_get_contents(__DIR__ . '/../resources/man-1351317_640.png');
    }

    /**
     * @return string
     */
    protected function getFilename()
    {
        return 'testFilesystem.txt';
    }

    /**
     * @param string $moreDirName
     * @return CsmIdent
     */
    protected function getIdent($moreDirName = '')
    {
        $res = CsmIdent::create()
            ->addResourceName('users')
            ->addString('Smith')
            ->addHash('some data')
            ->addNumeric(18990);
        if ($moreDirName != '') {
            $res->addResourceName($moreDirName);
        }
        return $res;
    }

    /**
     * @return string
     */
    protected function getFullPath()
    {
        return $this->getReourceDirPath(true)
        . '/users/83/109/105/116/104/1e50/210a/0202/497f/b79b/c38b/6ade/6c34/18/99/0';
    }

    /**
     * @param string $filename
     * @return string
     */
    protected function getUrl($filename)
    {
        return 'http://localhost/test/users/83/109/105/116/104/1e50/210a/0202/497f/b79b/c38b/6ade/6c34/18/99/0/'
            .$filename;
    }

    /**
     * @return void
     */
    protected function setUp()
    {
        mkdir($this->getReourceDirPath());
    }

    protected function tearDown()
    {
        $this->rrmdir($this->getReourceDirPath(true));
    }

    /**
     * @return string
     */
    protected function getReourceDirPath($normalize = false)
    {
        $dir = __DIR__ . '/../resources/testFilesystem';
        return $normalize ? realpath($dir) : $dir;
    }

    /**
     * @param string $src
     */
    public function rrmdir($src)
    {
        $dir = opendir($src);
        while (false !== ($file = readdir($dir))) {
            if (( $file != '.' ) && ( $file != '..' )) {
                $full = $src . '/' . $file;
                if (is_dir($full)) {
                    $this->rrmdir($full);
                } else {
                    unlink($full);
                }
            }
        }
        closedir($dir);
        rmdir($src);
    }
}
