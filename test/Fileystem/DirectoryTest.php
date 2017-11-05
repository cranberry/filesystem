<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class DirectoryTest extends TestCase
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

	public function expectedChildProvider()
	{
		return [
			[ Node::FILE, \Cranberry\Filesystem\File::class ],
			[ Node::DIRECTORY, \Cranberry\Filesystem\Directory::class ],
			[ Node::LINK, \Cranberry\Filesystem\Link::class ],
		];
	}

	public function testCreateDirectory()
	{
		$pathname = self::getTempPathname() . '/bar-' . microtime( true );

		$this->assertFalse( file_exists( $pathname ) );

		$directory = new Directory( $pathname );
		$returnValue = $directory->create();

		$this->assertTrue( $returnValue );
		$this->assertTrue( file_exists( $pathname ) );
	}

	/**
	 * @expectedException   InvalidArgumentException
	 */
	public function testCreateExistingDirectoryNonRecursivelyThrowsException()
	{
		$pathname = self::getTempPathname() . '/' . microtime( true );

		$this->assertFalse( file_exists( $pathname ) );

		$directory = new Directory( $pathname );
		$recursive = false;

		$directory->create( $recursive );
		$directory->create( $recursive );
	}

	public function testCreateExistingDirectoryRecursivelyReturnsTrue()
	{
		$pathname = self::getTempPathname() . '/' . microtime( true );

		$this->assertFalse( file_exists( $pathname ) );

		$directory = new Directory( $pathname );
		$recursive = true;

		$directory->create( $recursive );

		$this->assertTrue( $directory->create( $recursive ) );
	}

	/**
	 * @expectedException   InvalidArgumentException
	 */
	public function testCreateWithExistingUnwritableParentThrowsException()
	{
		$mockParent = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists','isWritable'] )
			->disableOriginalConstructor()
			->getMock();

		$mockParent
			->method( 'exists' )
			->willReturn( true );

		$mockParent
			->method( 'isWritable' )
			->willReturn( false );

		$mockDirectory = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists', 'getParent'] )
			->disableOriginalConstructor()
			->getMock();

		$mockDirectory
			->method( 'exists' )
			->willReturn( false );

		$mockDirectory
			->method( 'getParent' )
			->willReturn( $mockParent );

		$mockDirectory->create();
	}

	/**
	 * @expectedException   InvalidArgumentException
	 */
	public function testCreateWithNonExistingParentUnrecursivelyThrowsException()
	{
		$mockParent = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();

		$mockParent
			->method( 'exists' )
			->willReturn( false );

		$mockDirectory = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists', 'getParent'] )
			->disableOriginalConstructor()
			->getMock();

		$mockDirectory
			->method( 'exists' )
			->willReturn( false );

		$mockDirectory
			->method( 'getParent' )
			->willReturn( $mockParent );

		$recursive = false;
		$mockDirectory->create( $recursive );
	}

	/**
	 * @expectedException	Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_delete_throwsException_ifNodeDoesNotExist()
	{
		$directoryMock = $this
			->getMockBuilder( Directory::class )
			->disableOriginalConstructor( true )
			->setMethods( ['exists', 'isDeletable'] )
			->getMock();

		$directoryMock
			->method( 'exists' )
			->willReturn( false );

		$directoryMock
			->method( 'isDeletable' )
			->willReturn( false );

		$directoryMock->delete();
	}

	/**
	 * @expectedException	Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_delete_throwsException_ifNodeExistsButIsNotDeletable()
	{
		$directoryMock = $this
			->getMockBuilder( Directory::class )
			->disableOriginalConstructor( true )
			->setMethods( ['exists', 'isDeletable'] )
			->getMock();

		$directoryMock
			->method( 'exists' )
			->willReturn( true );

		$directoryMock
			->method( 'isDeletable' )
			->willReturn( false );

		$directoryMock->delete();
	}

	public function testDeleteRemovesDirectory()
	{
		$testParentPathname = self::getTempPathname() . '/' . microtime( true );
		mkdir( $testParentPathname, 0777, true );

		$this->assertTrue( file_exists( $testParentPathname ) );

		$directory = new Directory( $testParentPathname );
		$returnValue = $directory->delete();

		$this->assertTrue( $returnValue );
		$this->assertFalse( file_exists( $testParentPathname ) );
	}

	public function testDeleteNonEmptyDirectoryDeletesChildren()
	{
		$testParentPathname = self::getTempPathname() . '/' . microtime( true );
		mkdir( $testParentPathname, 0777, true );

		$this->assertTrue( file_exists( $testParentPathname ) );

		/* Children */
		$testSubfolderPathname = $testParentPathname . '/bar';
		mkdir( $testSubfolderPathname, 0777, true );

		$testChildFilename = $testParentPathname . '/foo.txt';
		touch( $testChildFilename );

		$directory = new Directory( $testParentPathname );
		$returnValue = $directory->delete();

		$this->assertTrue( $returnValue );
		$this->assertFalse( file_exists( $testParentPathname ) );
	}

	/**
	 * @expectedException   InvalidArgumentException
	 */
	public function testDeleteUnreadableDirectoryThrowsException()
	{
		$testPathname = self::getTempPathname() . '/noread-' . microtime( true );
		mkdir( $testPathname, 0311, true );

		$this->assertTrue( file_exists( $testPathname ) );

		$directory = new Directory( $testPathname );
		$directory->delete();
	}

	/**
	 * @dataProvider	expectedChildProvider
	 */
	public function test_getChild_returnsNodeObject_forNonExistentPathname( $type, $expectedClass )
	{
		$directory = new Directory( time() );
		$child = $directory->getChild( 'foo', $type );

		$this->assertInstanceOf( $expectedClass, $child );
	}

	public function provider_getChild_returnsNodeObject_forExistingPathname() : array
	{
		$pathname = sprintf( '%s/getChild_forExistingPathname-%s', self::getTempPathname(), microtime( true ) );
		mkdir( $pathname );

		$dirPathname = sprintf( '%s/dir-%s', $pathname, microtime( true ) );
		mkdir( $dirPathname );

		$linkDirPathname = sprintf( '%s/link-dir-%s', $pathname, microtime( true ) );
		symlink( $dirPathname, $linkDirPathname );

		$filePathname = sprintf( '%s/file-%s', $pathname, microtime( true ) );
		touch( $filePathname );

		$linkFilePathname = sprintf( '%s/link-file-%s', $pathname, microtime( true ) );
		symlink( $filePathname, $linkFilePathname );

		/* Symlink with missing target */
		$missingFilePathname = sprintf( '%s/file-%s', $pathname, microtime( true ) );
		touch( $missingFilePathname );

		$linkMissingFilePathname = sprintf( '%s/link-file-%s', $pathname, microtime( true ) );
		symlink( $missingFilePathname, $linkMissingFilePathname );

		unlink( $missingFilePathname );

		return [
			[$dirPathname, Directory::Class],
			[$filePathname, File::Class],
			[$linkFilePathname, Link::Class],
			[$linkDirPathname, Link::Class],
			[$linkMissingFilePathname, Link::Class],
		];
	}

	/**
	 * @dataProvider	provider_getChild_returnsNodeObject_forExistingPathname
	 */
	public function test_getChild_returnsNodeObject_forExistingPathname( string $pathname, string $expectedClass )
	{
		$parentDirname = dirname( $pathname );
		$childBasename = basename( $pathname );

		$parentDirectory = new Directory( $parentDirname );
		$childNode = $parentDirectory->getChild( $childBasename );

		$this->assertEquals( $expectedClass, get_class( $childNode ) );
	}

	public function testGetChildWithExistingNodeIgnoresTypeParam()
	{
		$directory = new Directory( self::getTempPathname() );

		$childFileBasename = '/file-' . microtime( true );
		$childFilePathname = self::getTempPathname() . $childFileBasename;
		touch( $childFilePathname );

		$childDirectoryBasename = '/dir-' . microtime( true );
		$childDirectoryPathname = self::getTempPathname() . $childDirectoryBasename;
		mkdir( $childDirectoryPathname );

		$this->assertTrue( file_exists( $childFilePathname ) );
		$this->assertTrue( file_exists( $childDirectoryPathname ) );

		$childFile = $directory->getChild( $childFileBasename, Node::DIRECTORY );
		$childDirectory = $directory->getChild( $childDirectoryBasename, Node::FILE );

		$this->assertInstanceOf( File::class, $childFile );
		$this->assertInstanceOf( Directory::class, $childDirectory );
	}

	/**
	 * @expectedException	BadMethodCallException
	 */
	public function testGetChildWithNonExistentNodeWithoutTypeParamThrowsException()
	{
		$directory = new Directory( self::getTempPathname() );
		$childFilename = microtime( true );
		$childPathname = self::getTempPathname() . '/' . $childFilename;

		$this->assertFalse( file_exists( $childPathname ) );

		$directory->getChild( $childFilename );
	}

	public function testGetChildrenDoesNotReturnDots()
	{
		$directory = new Directory( self::getTempPathname() );
		$children = $directory->getChildren();

		foreach( $children as $node )
		{
			$this->assertNotEquals( '.', $node->getFilename() );
			$this->assertNotEquals( '..', $node->getFilename() );
		}
	}

	public function testGetChildrenReturnsArray()
	{
		$directory = new Directory( self::getTempPathname() );
		$children = $directory->getChildren();

		$this->assertTrue( is_array( $children ) );
	}

	public function test_getChildren_returnsExpectedTypes()
	{
		/* Create parent directory on which to call getChildren */
		$parentPathname = sprintf( '%s/%s', self::getTempPathname(), microtime( true ) );
		mkdir( $parentPathname );
		$this->assertTrue( file_exists( $parentPathname ) );

		/* Create directory... */
		$childDirectoryPathname = sprintf( '%s/dir-%s', $parentPathname, microtime( true ) );
		mkdir( $childDirectoryPathname );
		$this->assertTrue( file_exists( $childDirectoryPathname ) );

		/* ...and a symlink to the directory */
		$childDirectoryLinkPathname = sprintf( '%s/link-%s', $parentPathname, microtime( true ) );
		symlink( $childDirectoryPathname, $childDirectoryLinkPathname );
		$this->assertTrue( is_link( $childDirectoryLinkPathname ) );

		/* Create file... */
		$childFilePathname = sprintf( '%s/file-%s', $parentPathname, microtime( true ) );
		touch( $childFilePathname );
		$this->assertTrue( file_exists( $childFilePathname ) );

		/* ...and a symlink to the file */
		$childFileLinkPathname = sprintf( '%s/link-%s', $parentPathname, microtime( true ) );
		symlink( $childFilePathname, $childFileLinkPathname );
		$this->assertTrue( is_link( $childFileLinkPathname ) );

		$parentDirectory = new Directory( $parentPathname );

		$children = $parentDirectory->getChildren();
		$this->assertEquals( 4, count( $children ) );

		foreach( $children as $childNode )
		{
			$nodeClass = get_class( $childNode );

			if( $childNode->isDir() )
			{
				$this->assertEquals( Directory::class, $nodeClass );
			}
			if( $childNode->isFile() )
			{
				$this->assertEquals( File::class, $nodeClass );
			}
			if( $childNode->isLink() )
			{
				$this->assertEquals( Link::class, $nodeClass );
			}
		}
	}

	/**
	 * @expectedException   InvalidArgumentException
	 */
	public function testGetChildrenOfNonExistentDirectoryThrowsException()
	{
		$mockDirectory = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();

		$mockDirectory
			->method( 'exists' )
			->willReturn( false );

		$children = $mockDirectory->getChildren();
	}

	/**
	 * @expectedException   InvalidArgumentException
	 */
	public function testGetChildrenOfUnreadableDirectoryThrowsException()
	{
		$mockDirectory = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists', 'isReadable'] )
			->disableOriginalConstructor()
			->getMock();

		$mockDirectory
			->method( 'exists' )
			->willReturn( true );

		$mockDirectory
			->method( 'isReadable' )
			->willReturn( false );

		$children = $mockDirectory->getChildren();
	}

	public function testGetChildrenReturnsArrayOfNodeObjects()
	{
		$testParentPathname = self::getTempPathname() . '/' . microtime( true );
		mkdir( $testParentPathname, 0777, true );

		$directory = new Directory( $testParentPathname );

		/* File */
		$testChildFilename = $testParentPathname . '/foo.txt';
		touch( $testChildFilename );

		$children = $directory->getChildren();
		$this->assertEquals( 1, count( $children ) );
		$this->assertInstanceOf( \Cranberry\Filesystem\File::class, $children[0] );

		unlink( $testChildFilename );

		/* Directory */
		$testChildPathname = $testParentPathname . '/bar';
		mkdir( $testChildPathname );

		$children = $directory->getChildren();
		$this->assertEquals( 1, count( $children ) );
		$this->assertInstanceOf( \Cranberry\Filesystem\Directory::class, $children[0] );
	}

	public function testGetChildrenWithFilter()
	{
		$testParentPathname = self::getTempPathname() . '/' . microtime( true );
		mkdir( $testParentPathname, 0777, true );

		$directory = new Directory( $testParentPathname );

		/* File */
		$testChildFilename = $testParentPathname . '/foo.txt';
		touch( $testChildFilename );

		/* Directory */
		$testChildPathname = $testParentPathname . '/bar';
		mkdir( $testChildPathname );

		/* Filter */
		$filter = function()
		{
			$node = $this->current();
			return $node->getBasename() == 'bar';
		};

		$children = $directory->getChildren( $filter );

		$this->assertEquals( 1, count( $children ) );
		$this->assertInstanceOf( \Cranberry\Filesystem\Directory::class, $children[0] );
		$this->assertEquals( 'bar', $children[0]->getBasename() );
	}

	public function testGetChildrenByFileExtension()
	{
		$testParentPathname = self::getTempPathname() . '/' . microtime( true );
		mkdir( $testParentPathname, 0777, true );

		$directory = new Directory( $testParentPathname );

		/* Files */
		$testChildFilename1 = $testParentPathname . '/foo.txt';
		touch( $testChildFilename1 );

		$testChildFilename2 = $testParentPathname . '/bar.php';
		touch( $testChildFilename2 );

		$testChildFilename3 = $testParentPathname . '/baz.yml';
		touch( $testChildFilename3 );

		$testChildFilename4 = $testParentPathname . '/boz.gif';
		touch( $testChildFilename4 );

		$extensions = ['php','yml'];
		$children = $directory->getChildrenByFileExtension( $extensions );

		$this->assertTrue( is_array( $children ) );
		$this->assertEquals( 2, count( $children ) );

		foreach( $children as $file )
		{
			$this->assertTrue( in_array( $file->getExtension(), $extensions ) );
		}
	}

	public function test_isDeletable_returnsFalseIfChildFileIsNotDeletable()
	{
		$childMock = $this
			->getMockBuilder( File::class )
			->disableOriginalConstructor( true )
			->setMethods( ['isDeletable'] )
			->getMock();
		$childMock
			->method( 'isDeletable' )
			->willReturn( false );

		$parentMock = $this
			->getMockBuilder( Directory::class )
			->disableOriginalConstructor( true )
			->setMethods( ['isExecutable', 'isWritable'] )
			->getMock();
		$parentMock
			->method( 'isExecutable' )
			->willReturn( true );
		$parentMock
			->method( 'isWritable' )
			->willReturn( true );

		$directoryMock = $this
			->getMockBuilder( Directory::class )
			->disableOriginalConstructor( true )
			->setMethods( ['exists', 'getChildren', 'getParent'] )
			->getMock();
		$directoryMock
			->method( 'exists' )
			->willReturn( true );
		$directoryMock
			->method( 'getChildren' )
			->willReturn( [$childMock] );
		$directoryMock
			->method( 'getParent' )
			->willReturn( $parentMock );

		$this->assertFalse( $directoryMock->isDeletable() );
	}

	public function testIsParentOfChildNodeReturnsTrue()
	{
		$parentDirectory = new Directory( self::getTempPathname() );

		$childNode = $parentDirectory
			->getChild( microtime( true ), Node::DIRECTORY )
			->getChild( microtime( true ), Node::DIRECTORY );

		$this->assertTrue( $parentDirectory->isParentOfNode( $childNode ) );
	}

	public function testIsParentOfRootNodeReturnsFalse()
	{
		$directory = new Directory( self::getTempPathname() );
		$rootDirectory = new Directory( '/' );

		$this->assertFalse( $directory->isParentOfNode( $rootDirectory ) );
	}

	public function testIsParentOfUnrelatedNodeReturnsFalse()
	{
		$parentDirectory = new Directory( self::getTempPathname() );

		$nonChildNode = $parentDirectory
			->getParent()
			->getChild( microtime( true ), Node::DIRECTORY );

		$this->assertFalse( $parentDirectory->isParentOfNode( $nonChildNode ) );
	}

	/**
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_INVALIDTARGET
	 */
	public function testMoveDirectoryToFileThrowsException()
	{
		$sourceDirectoryMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();

		$sourceDirectoryMock
			->method( 'exists' )
			->willReturn( true );

		$targetFileMock = $this
			->getMockBuilder( File::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();

		$targetFileMock
			->method( 'exists' )
			->willReturn( true );

		$sourceDirectoryMock->moveTo( $targetFileMock );
	}

	public function testMoveDirectoryToNonExistentDirectoryWithWritableParent()
	{
		$sourcePathname = self::getTempPathname() . '/source-' . microtime( true );
		$sourceDirectory = new Directory( $sourcePathname );
		$sourceDirectory->create();
		$this->assertTrue( $sourceDirectory->exists() );

		$parentDirectory = $sourceDirectory->getParent();
		$this->assertTrue( $parentDirectory->isWritable() );

		$targetDirectory = $parentDirectory->getChild( 'target-' . microtime( true ), Node::DIRECTORY );
		$this->assertFalse( $targetDirectory->exists() );

		$movedDirectory = $sourceDirectory->moveTo( $targetDirectory );

		$this->assertFalse( $sourceDirectory->exists() );
		$this->assertTrue( $movedDirectory->exists() );
		$this->assertEquals( $targetDirectory, $movedDirectory );
	}

	/**
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_INVALIDTARGET
	 */
	public function testMoveToChildDirectoryThrowsException()
	{
		$parentPathname = self::getTempPathname() . '/parent-' . microtime( true );
		$parentDirectory = new Directory( $parentPathname );
		$childDirectory = $parentDirectory->getChild( 'child-' . microtime( true ), Node::DIRECTORY );

		$parentDirectory->moveTo( $childDirectory );
	}

	/**
	 * @expectedException		InvalidArgumentException
	 * expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function testMoveDirectoryToNonExistentDirectoryWithUnwritableParentThrowsException()
	{
		$sourceDirectoryMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists','getPathname'] )
			->disableOriginalConstructor()
			->getMock();
		$sourceDirectoryMock
			->method( 'exists' )
			->willReturn( true );
		$sourceDirectoryMock
			->method( 'getPathname' )
			->willReturn( 'source-' . microtime( true ) );

		$targetDirectoryParentMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists','getPathname','isWritable'] )
			->disableOriginalConstructor()
			->getMock();
		$targetDirectoryParentMock
			->method( 'exists' )
			->willReturn( true );
		$sourceDirectoryMock
			->method( 'getPathname' )
			->willReturn( 'target-' . microtime( true ) );
		$targetDirectoryParentMock
			->method( 'isWritable' )
			->willReturn( false );

		$targetDirectoryMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists','getParent'] )
			->disableOriginalConstructor()
			->getMock();
		$targetDirectoryMock
			->method( 'exists' )
			->willReturn( false );
		$targetDirectoryMock
			->method( 'getParent' )
			->willReturn( $targetDirectoryParentMock );

		$sourceDirectoryMock->moveTo( $targetDirectoryMock );
	}

	public static function tearDownAfterClass()
	{
		$tempPathname = self::getTempPathname();

		if( file_exists( $tempPathname ) )
		{
			$command = sprintf( 'chmod -R 0755 %s', $tempPathname );
			exec( $command );

			$command = sprintf( 'rm -r %s', $tempPathname );
			exec( $command );
		}
	}
}
