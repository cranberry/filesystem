<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class LinkTest extends TestCase
{
	/**
	 * @var    string
	 */
	protected static $tempPathname;

	public static function setUpBeforeClass()
	{
		self::$tempPathname = dirname( __DIR__ ) . '/tmp';
		if( !file_exists( self::$tempPathname ) )
		{
			mkdir( self::$tempPathname, 0777, true );
		}
	}

	/**
	 * Creates a file and returns the full path
	 */
	static public function getTempFilePathname( string $filename )
	{
		$filePathname = sprintf( '%s/%s-source', self::$tempPathname, $filename );
		touch( $filePathname );

		return $filePathname;
	}

	/**
	 * Creates a file and a symlink to that file, and returns the symlink path
	 */
	static public function getTempLinkPathname( string $filePathname )
	{
		if( !file_exists( $filePathname ) )
		{
			throw new \Exception( 'Invalid source file: ' . $filePathname );
		}

		$linkFilename = sprintf( '%s/%s-target', self::$tempPathname, basename( $filePathname ) );
		symlink( $filePathname, $linkFilename );

		return $linkFilename;
	}

	/**
	 * @expectedException	Cranberry\Filesystem\PermissionsException
	 * @expectedExceptionCode	Cranberry\Filesystem\Node::ERROR_CODE_PERMISSIONS
	 */
	public function test_delete_throwsExceptionIfNotDeletable()
	{
		$linkMock = $this
			->getMockBuilder( Link::class )
			->disableOriginalConstructor( true )
			->setMethods( ['isDeletable'] )
			->getMock();

		$linkMock
			->method( 'isDeletable' )
			->willReturn( false );

		$linkMock->delete();
	}

	public function test_delete_unlinksLink()
	{
		$filePathname = self::getTempFilePathname( microtime( true ) );
		$linkPathname = self::getTempLinkPathname( $filePathname );

		$this->assertTrue( file_exists( $filePathname ) );
		$this->assertTrue( file_exists( $linkPathname ) );

		$link = new Link( $linkPathname );
		$link->delete();

		$this->assertTrue( file_exists( $filePathname ) );
		$this->assertFalse( file_exists( $linkPathname ) );
	}

	public function test_exists_withNonExistentLinkReturnsFalse()
	{
		$filePathname = self::getTempFilePathname( microtime( true ) );
		$linkPathname = self::getTempLinkPathname( $filePathname );

		unlink( $filePathname );
		unlink( $linkPathname );

		$this->assertFalse( file_exists( $filePathname ) );
		$this->assertFalse( is_link( $linkPathname ) );

		$link = new Link( $linkPathname );

		$this->assertFalse( $link->exists() );
	}

	public function test_exists_withNonExistentSourceReturnsTrue()
	{
		$filePathname = self::getTempFilePathname( microtime( true ) );
		$linkPathname = self::getTempLinkPathname( $filePathname );

		unlink( $filePathname );

		$this->assertFalse( file_exists( $filePathname ) );
		$this->assertTrue( is_link( $linkPathname ) );

		$link = new Link( $linkPathname );

		$this->assertTrue( $link->exists() );
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
