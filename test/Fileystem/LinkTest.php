<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

use PHPUnit\Framework\TestCase;

class LinkTest extends TestCase
{
	static public function getTempPathname()
	{
		$tempPathname = dirname( __DIR__ ) . '/tmp-Link';
		if( !file_exists( $tempPathname ) )
		{
			mkdir( $tempPathname, 0777, true );
		}

		return $tempPathname;
	}

	/**
	 * Creates a directory and returns the full path
	 */
	static public function getTempDirectoryPathname( string $pathname )
	{
		$dirPathname = sprintf( '%s/%s-source', self::getTempPathname(), $pathname );
		mkdir( $dirPathname );

		return $dirPathname;
	}

	/**
	 * Creates a file and returns the full path
	 */
	static public function getTempFilePathname( string $filename )
	{
		$filePathname = sprintf( '%s/%s-source', self::getTempPathname(), $filename );
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

		$linkFilename = sprintf( '%s/%s-link', self::getTempPathname(), basename( $filePathname ) );
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

	public function test_isDir_returnsFalse()
	{
		$dirPathname = self::getTempDirectoryPathname( microtime( true ) );
		$linkPathname = self::getTempLinkPathname( $dirPathname );

		$this->assertTrue( file_exists( $dirPathname ) );
		$this->assertTrue( is_link( $linkPathname ) );

		$link = new Link( $linkPathname );

		$this->assertTrue( $link->isLink() );
		$this->assertFalse( $link->isDir() );
		$this->assertFalse( $link->isFile() );
	}

	public function test_isFile_returnsFalse()
	{
		$filePathname = self::getTempFilePathname( microtime( true ) );
		$linkPathname = self::getTempLinkPathname( $filePathname );

		$this->assertTrue( file_exists( $filePathname ) );
		$this->assertTrue( is_link( $linkPathname ) );

		$link = new Link( $linkPathname );

		$this->assertTrue( $link->isLink() );
		$this->assertFalse( $link->isDir() );
		$this->assertFalse( $link->isFile() );
	}

	public function provider_targetIs_methodsReturnBool() : array
	{
		$filePathname = self::getTempFilePathname( 'file-' . microtime( true ) );
		$fileLinkPathname = self::getTempLinkPathname( $filePathname );

		$dirPathname = self::getTempDirectoryPathname( 'dir-' . microtime( true ) );
		$dirLinkPathname = self::getTempLinkPathname( $dirPathname );

		$linkDirLinkPathname = self::getTempLinkPathname( $dirLinkPathname );
		$linkFileLinkPathname = self::getTempLinkPathname( $fileLinkPathname );

		return [
			[$fileLinkPathname, true, false, false],
			[$dirLinkPathname, false, true, false],
			[$linkDirLinkPathname, false, false, true],
			[$linkFileLinkPathname, false, false, true],
		];
	}

	/**
	 * @dataProvider	provider_targetIs_methodsReturnBool
	 */
	public function test_targetIs_methodsReturnBool( string $linkPathname, bool $targetIsFile, bool $targetIsDir, bool $targetIsLink )
	{
		$link = new Link( $linkPathname );

		$this->assertTrue( $link->isLink() );

		$this->assertEquals( $targetIsFile, $link->targetIsFile() );
		$this->assertEquals( $targetIsDir,  $link->targetIsDir() );
		$this->assertEquals( $targetIsLink, $link->targetIsLink() );
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
