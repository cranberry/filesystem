<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class NodeTest extends TestCase
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

	public function sourceNodeProvider()
	{
		// Same value as self::getTempPathname(), which isn't accessible to
		//   @dataProvider methods
		$tempPathname = self::getTempPathname();

		$sourcePathname = $tempPathname . '/dir-' . microtime( true );
		$sourceDirectory = new Directory( $sourcePathname );

		$sourceFilename = $tempPathname . '/file-' . microtime( true );
		$sourceFile = new File( $sourceFilename );

		return [
			[$sourceDirectory],
			[$sourceFile],
		];
	}

	/**
	 * @expectedException	Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_createNodeFromPathname_throwsExceptionForNonExistentPath()
	{
		$pathname = sprintf( '%s/%s', self::getTempPathname(), microtime( true ) );

		$this->assertFalse( file_exists( $pathname ) );

		$node = Node::createNodeFromPathname( $pathname );
	}

	public function provider_createNodeFromPathname() : array
	{
		$pathname = sprintf( '%s/createNodeFromPathname-%s', self::getTempPathname(), microtime( true ) );
		mkdir( $pathname );

		$dirPathname = sprintf( '%s/dir-%s', $pathname, microtime( true ) );
		mkdir( $dirPathname );

		$filePathname = sprintf( '%s/file-%s', $pathname, microtime( true ) );
		touch( $filePathname );

		$linkDirPathname = sprintf( '%s/link-dir-%s', $pathname, microtime( true ) );
		symlink( $dirPathname, $linkDirPathname );

		$linkFilePathname = sprintf( '%s/link-file-%s', $pathname, microtime( true ) );
		symlink( $filePathname, $linkFilePathname );

		return [
			[$dirPathname, Directory::Class],
			[$filePathname, File::Class],
			[$linkFilePathname, Link::Class],
			[$linkDirPathname, Link::Class],
		];
	}

	/**
	 * @dataProvider	provider_createNodeFromPathname
	 */
	public function test_createNodeFromPathname( string $pathname, string $expectedClass )
	{
		$node = Node::createNodeFromPathname( $pathname );
		$this->assertEquals( $expectedClass, get_class( $node ) );
	}

	public function test_isDeletable_returnsFalseForNonExistentNode()
	{
		$nodeMock = $this->getMockForAbstractClass( Node::class, [], '', false, true, true, ['exists'] );
		$this->assertFalse( $nodeMock->isDeletable() );
	}

	public function provider_isDeletable_usesParentWriteExecutePermissions() : array
	{
		return [
			[true, true],
			[true, false],
			[false, true],
			[false, false],
		];
	}

	/**
	 * @dataProvider	provider_isDeletable_usesParentWriteExecutePermissions
	 */
	public function test_isDeletable_usesParentWriteExecutePermissions( bool $parentW, bool $parentX )
	{
		$parentMock = $this
			->getMockBuilder( Directory::class )
			->disableOriginalConstructor( true )
			->setMethods( ['isExecutable','isWritable'] )
			->getMock();
		$parentMock
			->method( 'isExecutable' )
			->willReturn( $parentX );
		$parentMock
			->method( 'isWritable' )
			->willReturn( $parentW );

		$nodeMock = $this->getMockForAbstractClass( Node::class, [], '', false, true, true, ['exists','getParent'] );
		$nodeMock
			->method( 'exists' )
			->willReturn( true );
		$nodeMock
			->method( 'getParent' )
			->willReturn( $parentMock );

		$shouldBeDeletable = $parentW && $parentX;

		$this->assertEquals( $shouldBeDeletable, $nodeMock->isDeletable() );
	}

	public function testConstructorReplacesLeadingTildeWithHomeDirectory()
	{
		$filename = 'file-' . time();
		$pathname = sprintf( '~/%s', $filename );
		$fullPathname = sprintf( '%s/%s', getenv( 'HOME' ), $filename );

		$mockNode = $this->getMockForAbstractClass( Node::class, [$pathname] );

		$this->assertEquals( $fullPathname, $mockNode->getPathname() );
	}

	public function testConstructorDoesNotReplaceNonLeadingTilde()
	{
		$pathname = 'file~-' . time();
		$mockNode = $this->getMockForAbstractClass( Node::class, [$pathname] );

		$this->assertEquals( $pathname, $mockNode->getPathname() );
	}

	public function testExists()
	{
		$pathname = self::getTempPathname() . '/' . microtime( true );

		$mockNode = $this->getMockForAbstractClass( Node::class, [], '', false, true, true, ['getPathname'] );
		$mockNode
			->method( 'getPathname' )
			->willReturn( $pathname );

		$this->assertFalse( $mockNode->exists() );

		touch( $pathname );

		$this->assertTrue( $mockNode->exists() );
	}

	public function testGetParentOfRootReturnsFalse()
	{
		$directory = new Directory( '/' );

		$this->assertFalse( $directory->getParent() );
	}

	public function testGetParentReturnsDirectoryObject()
	{
		$pathname = self::getTempPathname() . '/' . microtime( true );

		$mockNode = $this->getMockForAbstractClass( Node::class, [], '', false, true, true, ['getPathname'] );
		$mockNode
			->method( 'getPathname' )
			->willReturn( $pathname );

		$parentNode = $mockNode->getParent();

		$this->assertInstanceOf( Directory::class, $parentNode );
		$this->assertEquals( dirname( $pathname ), $parentNode->getPathname() );
	}

	/**
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_moveTo_existingUnwritableNode_throwsException()
	{
		$sourceNodeMock = $this->getMockForAbstractClass
		(
			Node::class,
			[],				// Constructor arguments
			'',				// Mock class name
			false,			// Call original constructor
			true,			// Call original clone
			true,			// Call autoload
			['exists']		// Mocked methods
		);
		$sourceNodeMock
			->method( 'exists' )
			->willReturn( true );

		$targetNodeMock = $this->getMockForAbstractClass
		(
			Node::class,
			[],						// Constructor arguments
			'',						// Mock class name
			false,					// Call original constructor
			true,					// Call original clone
			true,					// Call autoload
			['exists','isWritable']	// Mocked methods
		);
		$targetNodeMock
			->method( 'exists' )
			->willReturn( true );
		$targetNodeMock
			->method( 'isWritable' )
			->willReturn( false );

		$sourceNodeMock->moveTo( $targetNodeMock );
	}

	/**
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_moveTo_nodeWithNonExistentParent_throwsException()
	{
		$sourceNodeMock = $this->getMockForAbstractClass
		(
			Node::class,
			[],				// Constructor arguments
			'',				// Mock class name
			false,			// Call original constructor
			true,			// Call original clone
			true,			// Call autoload
			['exists']		// Mocked methods
		);
		$sourceNodeMock
			->method( 'exists' )
			->willReturn( true );

		$targetNodeParentMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists'] )
			->disableOriginalConstructor()
			->getMock();
		$targetNodeParentMock
			->method( 'exists' )
			->willReturn( false );

		$targetNodeMock = $this->getMockForAbstractClass
		(
			Node::class,
			[],						// Constructor arguments
			'',						// Mock class name
			false,					// Call original constructor
			true,					// Call original clone
			true,					// Call autoload
			['exists','getParent']	// Mocked methods
		);
		$targetNodeMock
			->method( 'exists' )
			->willReturn( false );
		$targetNodeMock
			->method( 'getParent' )
			->willReturn( $targetNodeParentMock );

		$sourceNodeMock->moveTo( $targetNodeMock );
	}

	/**
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_moveTo_nodeWithUnwritableParent_throwsException()
	{
		$sourceNodeMock = $this->getMockForAbstractClass
		(
			Node::class,
			[],				// Constructor arguments
			'',				// Mock class name
			false,			// Call original constructor
			true,			// Call original clone
			true,			// Call autoload
			['exists']		// Mocked methods
		);
		$sourceNodeMock
			->method( 'exists' )
			->willReturn( true );

		$targetNodeParentMock = $this
			->getMockBuilder( Directory::class )
			->setMethods( ['exists','isWritable'] )
			->disableOriginalConstructor()
			->getMock();
		$targetNodeParentMock
			->method( 'exists' )
			->willReturn( true );
		$targetNodeParentMock
			->method( 'isWritable' )
			->willReturn( false );

		$targetNodeMock = $this->getMockForAbstractClass
		(
			Node::class,
			[],						// Constructor arguments
			'',						// Mock class name
			false,					// Call original constructor
			true,					// Call original clone
			true,					// Call autoload
			['exists','getParent']	// Mocked methods
		);
		$targetNodeMock
			->method( 'exists' )
			->willReturn( false );
		$targetNodeMock
			->method( 'getParent' )
			->willReturn( $targetNodeParentMock );

		$sourceNodeMock->moveTo( $targetNodeMock );
	}

	/**
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_moveTo_theoreticalNonExistentRoot_throwsException()
	{
		$sourceNodeMock = $this->getMockForAbstractClass
		(
			Node::class,
			[],				// Constructor arguments
			'',				// Mock class name
			false,			// Call original constructor
			true,			// Call original clone
			true,			// Call autoload
			['exists']		// Mocked methods
		);
		$sourceNodeMock
			->method( 'exists' )
			->willReturn( true );

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
			->willReturn( false );

		$sourceNodeMock->moveTo( $targetDirectoryMock );
	}

	/**
	 * @dataProvider	sourceNodeProvider
	 */
	public function testMoveNodeToWritableDirectory( Node $sourceNode )
	{
		$sourceNode->create();
		$this->assertTrue( $sourceNode->exists() );

		$targetPathname = self::getTempPathname() . '/target-' . microtime( true );
		$targetDirectory = new Directory( $targetPathname );
		$targetDirectory->create();

		$this->assertTrue( $targetDirectory->isWritable() );

		$newNode = $sourceNode->moveTo( $targetDirectory );
		$this->assertFalse( $sourceNode->exists() );
		$this->assertTrue( $newNode->exists() );
		$this->assertEquals( $targetDirectory, $newNode->getParent() );
		$this->assertEquals( $sourceNode->getBasename(), $newNode->getBasename() );
	}

	/**
	 * @expectedException		Cranberry\Filesystem\Exception
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function test_moveTo_usingNonExistentSourceNode_throwsException()
	{
		$sourceNodeMock = $this->getMockForAbstractClass( Node::class, [], '', false, true, true, ['exists','getPathname'] );
		$sourceNodeMock
			->method( 'exists' )
			->willReturn( false );
		$sourceNodeMock
			->method( 'getPathname' )
			->willReturn( microtime( true ) );

		$targetNodeMock = $this->getMockForAbstractClass( Node::class, [microtime( true )] );

		$sourceNodeMock->moveTo( $targetNodeMock );
	}

	/**
	 * @dataProvider    sourceNodeProvider
	 */
	public function testMoveNodeToExistingDirectoryCreatesChild( Node $sourceNode )
	{
		$targetPathname = self::getTempPathname() . '/target-' . microtime( true );
		$targetDirectory = new Directory( $targetPathname );
		$targetDirectory->create( true );

		$sourceNode->create();

		$newNode = $sourceNode->moveTo( $targetDirectory );

		$this->assertEquals( get_class( $sourceNode ), get_class( $newNode ) );
		$this->assertEquals( $sourceNode->getBasename(), $newNode->getBasename() );
		$this->assertEquals( $targetDirectory, $newNode->getParent() );
	}

	public function testSetPerms()
	{
		$pathname = self::getTempPathname() . '/' . microtime( true );
		mkdir( $pathname, 0600 );

		$mockNode = $this->getMockForAbstractClass( Node::class, [$pathname] );

		$originalPermissions = (int)substr( sprintf( '%o', $mockNode->getPerms() ), -4 );

		$newPermissionsOctal = 0755;
		$expectedPermissionsInt = 755;

		$this->assertNotEquals( $originalPermissions, $expectedPermissionsInt );

		$mockNode->setPerms( $newPermissionsOctal );
		clearstatcache(); // The results of getPerms() are cached
		$actualPermissionsInt = (int)substr( sprintf( '%o', $mockNode->getPerms() ), -4 );

		$this->assertEquals( $expectedPermissionsInt, $actualPermissionsInt );
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
