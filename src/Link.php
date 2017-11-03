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
}
