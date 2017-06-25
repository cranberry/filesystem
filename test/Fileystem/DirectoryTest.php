<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class DirectoryTest extends TestCase
{
    /**
     * @var    string
     */
    protected static $tempPathname;

	public function expectedChildProvider()
	{
		return [
			[ null, \Cranberry\Filesystem\File::class ],
			[ Node::FILE, \Cranberry\Filesystem\File::class ],
			[ Node::DIRECTORY, \Cranberry\Filesystem\Directory::class ],
		];
	}

    public static function setUpBeforeClass()
    {
        self::$tempPathname = dirname( __DIR__ ) . '/fixtures/temp';
        if( !file_exists( self::$tempPathname ) )
        {
            mkdir( self::$tempPathname, 0777, true );
        }
    }

    public function testCreateDirectory()
    {
        $pathname = self::$tempPathname . '/bar';

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
        $pathname = self::$tempPathname . '/' . microtime( true );

        $this->assertFalse( file_exists( $pathname ) );

        $directory = new Directory( $pathname );
        $recursive = false;

        $directory->create( $recursive );
        $directory->create( $recursive );
    }

    public function testCreateExistingDirectoryRecursivelyReturnsTrue()
    {
        $pathname = self::$tempPathname . '/' . microtime( true );

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
    public function testDeleteRemovesDirectory()
    {
        $testParentPathname = self::$tempPathname . '/' . microtime( true );
        mkdir( $testParentPathname, 0777, true );

        $this->assertTrue( file_exists( $testParentPathname ) );

        $directory = new Directory( $testParentPathname );
        $returnValue = $directory->delete();

        $this->assertTrue( $returnValue );
        $this->assertFalse( file_exists( $testParentPathname ) );
    }

    public function testDeleteNonEmptyDirectoryDeletesChildren()
    {
        $testParentPathname = self::$tempPathname . '/' . microtime( true );
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
        $testPathname = self::$tempPathname . '/noread-' . microtime( true );
        mkdir( $testPathname, 0311, true );

        $this->assertTrue( file_exists( $testPathname ) );

        $directory = new Directory( $testPathname );
        $directory->delete();
    }

	/**
	 * @dataProvider	expectedChildProvider
	 */
	public function testGetChildReturnsNodeObject( $type, $expectedClass )
	{
		$directory = new Directory( time() );
		$child = $directory->getChild( 'foo', $type );

		$this->assertInstanceOf( $expectedClass, $child );
	}

    public function testGetChildWithExistingNodeOverridesTypeParam()
    {
        $directory = new Directory( self::$tempPathname );

        $childFileBasename = '/file-' . microtime( true );
        $childFilePathname = self::$tempPathname . $childFileBasename;
        touch( $childFilePathname );

        $childDirectoryBasename = '/dir-' . microtime( true );
        $childDirectoryPathname = self::$tempPathname . $childDirectoryBasename;
        mkdir( $childDirectoryPathname );

        $this->assertTrue( file_exists( $childFilePathname ) );
        $this->assertTrue( file_exists( $childDirectoryPathname ) );

        $childFile = $directory->getChild( $childFileBasename, Node::DIRECTORY );
        $childDirectory = $directory->getChild( $childDirectoryBasename );

        $this->assertInstanceOf( File::class, $childFile );
        $this->assertInstanceOf( Directory::class, $childDirectory );
    }

    public function testGetChildrenDoesNotReturnDots()
    {
        $directory = new Directory( self::$tempPathname );
        $children = $directory->getChildren();

        foreach( $children as $node )
        {
            $this->assertNotEquals( '.', $node->getFilename() );
            $this->assertNotEquals( '..', $node->getFilename() );
        }
    }

    public function testGetChildrenReturnsArray()
    {
        $directory = new Directory( self::$tempPathname );
        $children = $directory->getChildren();

        $this->assertTrue( is_array( $children ) );
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
        $testParentPathname = self::$tempPathname . '/' . microtime( true );
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
        $testParentPathname = self::$tempPathname . '/' . microtime( true );
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
        $testParentPathname = self::$tempPathname . '/' . microtime( true );
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

    public static function tearDownAfterClass()
    {
        if( file_exists( self::$tempPathname ) )
        {
            $command = sprintf( 'chmod -R 0755 %s', self::$tempPathname );
            exec( $command );

            $command = sprintf( 'rm -r %s', self::$tempPathname );
            exec( $command );
        }
    }
}
