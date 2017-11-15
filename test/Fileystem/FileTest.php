<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class FileTest extends TestCase
{
	static public function getTempPathname()
	{
		$tempPathname = sprintf( '%s/tmp-%s', dirname( __DIR__ ), str_replace( '\\', '_', __CLASS__ ) );
		if( !file_exists( $tempPathname ) )
		{
			mkdir( $tempPathname, 0777, true );
		}

		return $tempPathname;
	}

	public function testCreateFile()
	{
		$filename = self::getTempPathname() . '/createFile-' . microtime( true );

		$this->assertFalse( file_exists( $filename ) );

		$file = new File( $filename );
		$returnValue = $file->create();

		$this->assertTrue( $returnValue );
		$this->assertTrue( file_exists( $filename ) );
	}

	/**
	 * @expectedException  Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_INVALIDTARGET
	 */
	public function test_create_childOfNonExistentDirectory_nonRecursively_throwsException()
	{
		$filename = self::getTempPathname() . '/dir-' . microtime( true ) . '/foo.txt';

		$this->assertFalse( file_exists( dirname( $filename ) ) );
		$this->assertFalse( file_exists( $filename ) );

		$file = new File( $filename );
		$file->create();
	}

	public function testCreateFileRecursively()
	{
		$filename = self::getTempPathname() . '/recursive-' . microtime( true ) . '/foo.txt';

		$this->assertFalse( file_exists( dirname( $filename ) ) );
		$this->assertFalse( file_exists( $filename ) );

		$file = new File( $filename );
		$returnValue = $file->create( true );

		$this->assertTrue( $returnValue );
		$this->assertTrue( file_exists( $filename ) );
	}

	/**
	 * @expectedException	Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_delete_throwsException_ifNodeDoesNotExist()
	{
		$fileMock = $this
			->getMockBuilder( File::class )
			->disableOriginalConstructor( true )
			->setMethods( ['exists', 'isDeletable'] )
			->getMock();

		$fileMock
			->method( 'exists' )
			->willReturn( false );

		$fileMock
			->method( 'isDeletable' )
			->willReturn( false );

		$fileMock->delete();
	}

	/**
	 * @expectedException	Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_delete_throwsException_ifNodeExistsButIsNotDeletable()
	{
		$fileMock = $this
			->getMockBuilder( File::class )
			->disableOriginalConstructor( true )
			->setMethods( ['exists', 'isDeletable'] )
			->getMock();

		$fileMock
			->method( 'exists' )
			->willReturn( true );

		$fileMock
			->method( 'isDeletable' )
			->willReturn( false );

		$fileMock->delete();
	}

	public function testDeleteUnlinksFile()
	{
		$testParentPathname = self::getTempPathname() . '/' . microtime( true );
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
		$testFilename = self::getTempPathname() . '/getContents-' . microtime( true );

		$this->assertFalse( file_exists( $testFilename ) );

		$file = new File( $testFilename );
		$contents = microtime( true );
		file_put_contents( $testFilename, $contents );

		$this->assertEquals( (string)$contents, $file->getContents() );
	}

	/**
	 * @expectedException  Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_getContents_ofNonExistentFile_throwsException()
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
	 * @expectedException  Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_getContents_ofUnreadableFile_throwsException()
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

	public function testMoveFileToExistingWritableFile()
	{
		$sourceFilename = self::getTempPathname() . '/source-' . microtime( true );
		$sourceFile = new File( $sourceFilename );

		$sourceFileContents = 'source-contents-' . microtime( true );
		$sourceFile->putContents( $sourceFileContents );

		$this->assertTrue( $sourceFile->exists() );
		$this->assertEquals( $sourceFileContents, $sourceFile->getContents() );

		$targetFilename = self::getTempPathname() . '/target-' . microtime( true );
		$targetFile = new File( $targetFilename );

		$targetFileContents = 'target-contents-' . microtime( true );
		$targetFile->putContents( $targetFileContents );

		$newFile = $sourceFile->moveTo( $targetFile );

		$this->assertFalse( $sourceFile->exists() );
		$this->assertTrue( $newFile->exists() );
		$this->assertEquals( $targetFile, $newFile );

		$newFile->getContents();
		$this->assertEquals( $sourceFileContents, $newFile->getContents() );
	}

	/**
	 * Note: This covers both existent and non-existent target Files
	 *
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_moveTo_fileWithUnwritableParent_throwsException()
	{
		$sourceFileMock = $this
			->getMockBuilder( File::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();
		$sourceFileMock
			->method( 'exists' )
			->willReturn( true );

		/* Target */
		$targetFileParentMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['isWritable'] )
			->disableOriginalConstructor()
			->getMock();
		$targetFileParentMock
			->method( 'isWritable' )
			->willReturn( false );

		$targetFileMock = $this
			->getMockBuilder( File::class )
			->setMethods( ['exists','getParent'] )
			->disableOriginalConstructor()
			->getMock();
		$targetFileMock
			->method( 'exists' )
			->willReturn( true );
		$targetFileMock
			->method( 'getParent' )
			->willReturn( $targetFileParentMock );

		$sourceFileMock->moveTo( $targetFileMock );
	}

	/**
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_moveTo_nonExistentDirectory_throwsException()
	{
		$sourceFileMock = $this
			->getMockBuilder( File::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();
		$sourceFileMock
			->method( 'exists' )
			->willReturn( true );

		$targetDirectoryMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();
		$targetDirectoryMock
			->method( 'exists' )
			->willReturn( false );

		$sourceFileMock->moveTo( $targetDirectoryMock );
	}

	public function testMoveFileToNonExistentFileWithWritableParent()
	{
		$sourceFilename = self::getTempPathname() . '/source-' . microtime( true );
		$sourceFile = new File( $sourceFilename );
		$sourceFile->create();
		$this->assertTrue( $sourceFile->exists() );

		$sourceFileParent = $sourceFile->getParent();
		$this->assertTrue( $sourceFileParent->isWritable() );

		$targetFile = $sourceFileParent->getChild( 'target-' . microtime( true ), Node::FILE );
		$this->assertFalse( $targetFile->exists() );

		$newFile = $sourceFile->moveTo( $targetFile );

		$this->assertFalse( $sourceFile->exists() );
		$this->assertTrue( $newFile->exists() );
		$this->assertEquals( $targetFile, $newFile );
	}

	/**
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_putContents_usingUnwriteableFile_throwsException()
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
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_putContents_usingUnwritableParent_throwsException()
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

	public static function tearDownAfterClass()
	{
		$tempPathname = self::getTempPathname();
		if( file_exists( $tempPathname ) )
		{
			$command = sprintf( 'rm -r %s', $tempPathname );
			exec( $command );
		}
	}
}
