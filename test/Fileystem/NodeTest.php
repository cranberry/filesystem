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
