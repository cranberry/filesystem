<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

class File extends Node
{
	const ERROR_STRING_GETCONTENTS = 'Cannot get contents of file %s: %s.';
	const ERROR_STRING_PUTCONTENTS = 'Cannot write to file %s: %s.';

	/**
	 * Attempts to create file
	 *
	 * @param   boolean $recursive
	 *
	 * @param   int     $time
	 *
	 * @param   int     $atime
	 *
	 * @throws	Cranberry\Filesystem\Exception	If parent directory does not exist, and not creating recursively
	 *
	 * @return  boolean
	 */
	public function create( $recursive=false, $time=null, $atime=null )
	{
		if( !$this->getParent()->exists() )
		{
			if( $recursive )
			{
				$this->getParent()->create( true );
			}
			else
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_CREATE, $this->getPathname(), 'Parent does not exist' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_INVALIDTARGET );
			}
		}

		return touch( $this->getPathname(), $time, $atime );
	}

	/**
	 * Deletes file
	 *
	 * @throws	Cranberry\Filesystem\Exception	If not deletable
	 *
	 * @param    resource    $context
	 *
	 * @return   boolean
	 */
	public function delete( resource $context=null ) : bool
	{
		if( !$this->isDeletable() )
		{
			if( !$this->exists() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_DELETE, $this->getPathname(), 'No such file' );
				$exceptionCode = self::ERROR_CODE_NOSUCHNODE;
			}
			else
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_DELETE, $this->getPathname(), 'Permission denied' );
				$exceptionCode = self::ERROR_CODE_PERMISSIONS;
			}

			throw new Exception( $exceptionMessage, $exceptionCode );
		}

		if( $context == null )
		{
			$result = unlink( $this->getPathname() );
		}
		else
		{
			$result = unlink( $this->getPathname(), $context );
		}

		return $result;
	}

	/**
	 * Returns contents of file
	 *
	 * @param   boolean    $use_include_path
	 *
	 * @param   resource   $context
	 *
	 * @param   int        $offset
	 *
	 * @param   int        $maxlen
	 *
	 * @throws	Cranberry\Filesystem\Exception	If file does not exist
	 *
	 * @throws	Cranberry\Filesystem\Exception	If file is not readable
	 *
	 * @return  string
	 */
	public function getContents( $use_include_path=false, resource $context=null, $offset=0, $maxlen=null )
	{
		if( !$this->exists() )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_GETCONTENTS, $this->getPathname(), 'No such file' );
			throw new Exception( $exceptionMessage, self::ERROR_CODE_NOSUCHNODE );
		}

		if( !$this->isReadable() )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_GETCONTENTS, $this->getPathname(), 'Permission denied' );
			throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
		}

		if( $maxlen == null )
		{
			$contents = file_get_contents( $this->getPathname(), $use_include_path, $context, $offset );
		}
		else
		{
			$contents = file_get_contents( $this->getPathname(), $use_include_path, $context, $offset, $maxlen );
		}

		return $contents;
	}

	/**
	 * Performs File-specific validation on move request before handing off to
	 *   parent method
	 *
	 * @param   Cranberry\Filesystem\Node   $targetNode
	 *
	 * @throws	Cranberry\Filesystem\Exception	If target parent isn't writable
	 *
	 * @throws	Cranberry\Filesystem\Exception	If target directory doesn't exist
	 *
	 * @return  Node
	 */
	public function moveTo( Node $targetNode )
	{
		if( $targetNode instanceof File )
		{
			$targetParentNode = $targetNode->getParent();

			/* Can't move to file if parent isn't writable */
			if( !$targetParentNode->isWritable() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_MOVETO, $this->getPathname(), $targetParentNode->getPathname(), 'Permission denied' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
			}
		}
		if( $targetNode instanceof Directory )
		{
			if( !$targetNode->exists() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_MOVETO, $this->getPathname(), $targetNode->getPathname(), 'No such file or directory' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_NOSUCHNODE );
			}
		}

		return parent::moveTo( $targetNode );
	}

	/**
	 * Writes a string to the file
	 *
	 * @param   mixed       $data
	 *
	 * @param   int         $flags
	 *
	 * @param   resource    $flags
	 *
	 * @throws	Cranberry\Filesystem\Exception	If file exists and is not writable
	 *
	 * @throws	Cranberry\Filesystem\Exception	If file does not exist and parent is not writable
	 *
	 * @return  int|false
	 */
	public function putContents( $data, $flags=0, resource $context=null )
	{
		if( $this->exists() )
		{
			if( !$this->isWritable() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_PUTCONTENTS, $this->getPathname(), 'Permission denied' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
			}
		}
		else
		{
			if( !$this->getParent()->isWritable() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_PUTCONTENTS, $this->getParent()->getPathname(), 'Permission denied' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
			}
		}

		if( $context == null )
		{
			$result = file_put_contents( $this->getPathname(), $data, $flags );
		}
		else
		{
			$result = file_put_contents( $this->getPathname(), $data, $flags, $context );
		}

		return $result;
	}
}
