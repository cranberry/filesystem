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
		self::$tempPathname = dirname( __DIR__ ) . '/tmp-File';
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

	/**
	 * @expectedException	Cranberry\Filesystem\PermissionsException
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_delete_throwsExceptionIfNotDeletable()
	{
		$fileMock = $this
			->getMockBuilder( File::class )
			->disableOriginalConstructor( true )
			->setMethods( ['isDeletable'] )
			->getMock();

		$fileMock
			->method( 'isDeletable' )
			->willReturn( false );

		$fileMock->delete();
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

	public function testMoveFileToExistingWritableFile()
	{
		$sourceFilename = self::$tempPathname . '/source-' . microtime( true );
		$sourceFile = new File( $sourceFilename );

		$sourceFileContents = 'source-contents-' . microtime( true );
		$sourceFile->putContents( $sourceFileContents );

		$this->assertTrue( $sourceFile->exists() );
		$this->assertEquals( $sourceFileContents, $sourceFile->getContents() );

		$targetFilename = self::$tempPathname . '/target-' . microtime( true );
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
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function testMoveFileToFileWithUnwritableParentThrowsException()
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
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function testMoveFileToNonExistentDirectoryThrowsException()
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
		$sourceFilename = self::$tempPathname . '/source-' . microtime( true );
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

	public static function tearDownAfterClass()
	{
		if( file_exists( self::$tempPathname ) )
		{
			$command = sprintf( 'rm -r %s', self::$tempPathname );
			exec( $command );
		}
	}
}
