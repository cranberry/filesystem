<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class NodeTest extends TestCase
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

	public function sourceNodeProvider()
	{
		// Same value as self::$tempPathname, which isn't accessible to
		//   @dataProvider methods
		$tempPathname = dirname( __DIR__ ) . '/fixtures/temp';

		$sourcePathname = $tempPathname . '/dir-' . microtime( true );
		$sourceDirectory = new Directory( $sourcePathname );

		$sourceFilename = $tempPathname . '/file-' . microtime( true );
		$sourceFile = new File( $sourceFilename );

		return [
			[$sourceDirectory],
			[$sourceFile],
		];
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
		$pathname = self::$tempPathname . '/' . microtime( true );

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
		$pathname = self::$tempPathname . '/' . microtime( true );

		$mockNode = $this->getMockForAbstractClass( Node::class, [], '', false, true, true, ['getPathname'] );
		$mockNode
			->method( 'getPathname' )
			->willReturn( $pathname );

		$parentNode = $mockNode->getParent();

		$this->assertInstanceOf( Directory::class, $parentNode );
		$this->assertEquals( dirname( $pathname ), $parentNode->getPathname() );
	}

	/**
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function testMoveNodeToExistingUnwritableNodeThrowsException()
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
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function testMoveNodeToNodeWithNonExistentParentThrowsException()
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
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function testMoveNodeToTheoreticalNonExistentRootThrowsException()
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

		$targetPathname = self::$tempPathname . '/target-' . microtime( true );
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
	 * @expectedException		InvalidArgumentException
	 * @expectedExceptionCode	\Cranberry\Filesystem\Node::ERROR_CODE_NOSUCHNODE
	 */
	public function testMoveNonExistentSourceNodeThrowsException()
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
		$targetPathname = self::$tempPathname . '/target-' . microtime( true );
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
		$pathname = self::$tempPathname . '/' . microtime( true );
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
		if( file_exists( self::$tempPathname ) )
		{
			$command = sprintf( 'rm -r %s', self::$tempPathname );
			exec( $command );
		}
	}
}
