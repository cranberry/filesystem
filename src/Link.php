<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

class Link extends Node
{
	/**
	 * Deletes link
	 *
	 * @throws	Cranberry\Filesystem\PermissionsException	If not deletable
	 *
	 * @param    resource    $context
	 *
	 * @return   boolean
	 */
	public function delete() : bool
	{
		if( !$this->isDeletable() )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_DELETE, $this->getPathname(), 'Permission denied' );
			throw new PermissionsException( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
		}

		return unlink( $this->getPathname() );
	}

	/**
	 * Returns whether link exists at pathname
	 *
	 * @return	bool
	 */
	public function exists() : bool
	{
		return is_link( $this->getPathname() );
	}

	/**
	 * Overrides default behavior of SplFileInfo::isDir
	 *
	 * Use Link::targetIsDir to find whether linked node is a directory
	 *
	 * @return	bool
	 */
	public function isDir() : bool
	{
		return false;
	}

	/**
	 * Overrides default behavior of SplFileInfo::isFile
	 *
	 * Use Link::targetIsFile to find whether linked node is a file
	 *
	 * @return	bool
	 */
	public function isFile() : bool
	{
		return false;
	}

	/**
	 * Returns whether linked target is a directory
	 *
	 * @return	bool
	 */
	public function targetIsDir() : bool
	{
		$targetPathname = readlink( $this->getPathname() );

		if( is_link( $targetPathname ) )
		{
			return false;
		}

		return is_dir( $targetPathname );
	}

	/**
	 * Returns whether linked target is a file
	 *
	 * @return	bool
	 */
	public function targetIsFile() : bool
	{
		$targetPathname = readlink( $this->getPathname() );

		if( is_link( $targetPathname ) )
		{
			return false;
		}

		return is_file( $targetPathname );
	}

	/**
	 * Returns whether linked target is a link
	 *
	 * @return	bool
	 */
	public function targetIsLink() : bool
	{
		$targetPathname = readlink( $this->getPathname() );
		return is_link( $targetPathname );
	}
}
