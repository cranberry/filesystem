<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
    /**
     * @var    string
     */
    protected static $tempPathname;

    public static function setUpBeforeClass()
    {
        self::$tempPathname = dirname( __DIR__ ) . '/fixtures/temp';
        if( !file_exists( self::$tempPathname ) )
        {
            mkdir( self::$tempPathname, 0777, true );
        }
    }

    public function testCreateFile()
    {
        $filename = self::$tempPathname . '/createFile-' . microtime( true );

        $this->assertFalse( file_exists( $filename ) );

        $file = new File( $filename );
        $returnValue = $file->create();

        $this->assertTrue( $returnValue );
        $this->assertTrue( file_exists( $filename ) );
    }

    /**
     * @expectedException   InvalidArgumentException
     */
    public function testCreateChildOfNonExistentDirectoryThrowsException()
    {
        $filename = self::$tempPathname . '/dir-' . microtime( true ) . '/foo.txt';

        $this->assertFalse( file_exists( dirname( $filename ) ) );
        $this->assertFalse( file_exists( $filename ) );

        $file = new File( $filename );
        $file->create();
    }

    public function testCreateFileRecursively()
    {
        $filename = self::$tempPathname . '/recursive-' . microtime( true ) . '/foo.txt';

        $this->assertFalse( file_exists( dirname( $filename ) ) );
        $this->assertFalse( file_exists( $filename ) );

        $file = new File( $filename );
        $returnValue = $file->create( true );

        $this->assertTrue( $returnValue );
        $this->assertTrue( file_exists( $filename ) );
    }

    public function testDeleteUnlinksFile()
    {
        $testParentPathname = self::$tempPathname . '/' . microtime( true );
        mkdir( $testParentPathname, 0777, true );

        /* Files */
        $testChildFilename = $testParentPathname . '/foo.txt';
        touch( $testChildFilename );

        $this->assertTrue( file_exists( $testChildFilename ) );

        $file = new File( $testChildFilename );
        $returnValue = $file->delete();

        $this->assertTrue( $returnValue );
        $this->assertFalse( file_exists( $testChildFilename ) );
    }

    public function testGetContents()
    {
        $testFilename = self::$tempPathname . '/getContents-' . microtime( true );

        $this->assertFalse( file_exists( $testFilename ) );

        $file = new File( $testFilename );
        $contents = microtime( true );
        file_put_contents( $testFilename, $contents );

        $this->assertEquals( (string)$contents, $file->getContents() );
    }

	/**
	 * @expectedException  InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function testGetContentsOfNonExistentFileThrowsException()
	{
        $mockFile = $this
            ->getMockBuilder( File::class )
            ->setMethods( ['exists'] )
            ->disableOriginalConstructor()
            ->getMock();

        $mockFile
            ->method( 'exists' )
            ->willReturn( false );

        $fileContents = $mockFile->getContents();
	}

    /**
	 * @expectedException  InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function testGetContentsOfUnreadableFileThrowsException()
	{
        $mockFile = $this
            ->getMockBuilder( File::class )
            ->setMethods( ['exists','isReadable'] )
            ->disableOriginalConstructor()
            ->getMock();

        $mockFile
            ->method( 'exists' )
            ->willReturn( true );

        $mockFile
            ->method( 'isReadable' )
            ->willReturn( false );

        $fileContents = $mockFile->getContents();
	}

    /**
     * @expectedException   InvalidArgumentException
     */
    public function testPutContentsUsingUnwriteableFileThrowsException()
    {
        $mockFile = $this
            ->getMockBuilder( File::class )
            ->setMethods( ['exists', 'isWritable'] )
            ->disableOriginalConstructor()
            ->getMock();

        $mockFile
            ->method( 'exists' )
            ->willReturn( true );

        $mockFile
            ->method( 'isWritable' )
            ->willReturn( false );

        $didPutContents = $mockFile->putContents( time() );
    }

    /**
     * @expectedException   InvalidArgumentException
     */
    public function testPutContentsWithUnwritableParentThrowsException()
    {
        $mockParent = $this
            ->getMockBuilder( Directory::class )
            ->setMethods( ['isWritable'] )
            ->disableOriginalConstructor()
            ->getMock();

        $mockParent
            ->method( 'isWritable' )
            ->willReturn( false );

        $mockFile = $this
            ->getMockBuilder( File::class )
            ->setMethods( ['isWritable', 'getParent'] )
            ->disableOriginalConstructor()
            ->getMock();

        $mockFile
            ->method( 'isWritable' )
            ->willReturn( true );

        $mockFile
            ->method( 'getParent' )
            ->willReturn( $mockParent );

        $didPutContents = $mockFile->putContents( time() );
    }

    public function testPutContents()
    {
        $testFilename = self::$tempPathname . '/' . microtime( true );

        $this->assertFalse( file_exists( $testFilename ) );

        $file = new File( $testFilename );
        $contents = microtime( true );
        $bytesWritten = $file->putContents( $contents );

        $this->assertEquals( strlen( $contents ), $bytesWritten );
        $this->assertEquals( (string)$contents, file_get_contents( $testFilename ) );
    }

    public static function tearDownAfterClass()
    {
        if( file_exists( self::$tempPathname ) )
        {
            $command = sprintf( 'rm -r %s', self::$tempPathname );
            exec( $command );
        }
    }
}
