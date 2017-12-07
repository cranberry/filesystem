<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

abstract class Node extends \SplFileInfo
{
	const DIRECTORY = 1;
	const FILE = 2;
	const LINK = 4;

	const ERROR_CODE_PERMISSIONS = 1;
	const ERROR_CODE_NOSUCHNODE = 2;
	const ERROR_CODE_INVALIDTARGET = 4;
	const ERROR_CODE_NODEEXISTS = 8;

	const ERROR_STRING_COPY = 'Cannot copy %s: %s.';
	const ERROR_STRING_COPYTO = 'Cannot copy %s to %s: %s.';
	const ERROR_STRING_CREATE = 'Cannot create %s: %s.';
	const ERROR_STRING_CREATENODE = 'Cannot create node object for %s: %s.';
	const ERROR_STRING_DELETE = 'Cannot delete %s: %s.';
	const ERROR_STRING_MOVE = 'Cannot move %s: %s.';
	const ERROR_STRING_MOVETO = 'Cannot move %s to %s: %s.';
	const ERROR_STRING_NOSUCHNODE = 'No such %s %s.';

	/**
	 * Expand leading ~ to $HOME if necessary, then hand off to SplFileInfo
	 *
	 * @param	string	$pathname
	 * @return	void
	 */
	public function __construct( $pathname )
	{
		if( substr( $pathname, 0, 2 ) == '~/' )
		{
			$pathname = getenv( 'HOME' ) . substr( $pathname, 1 );
		}

		parent::__construct( $pathname );
	}

	/**
	 * Creates correctly typed node object from $pathname
	 *
	 * @param	string	$pathname
	 *
	 * @throws	Cranberry\Filesystem\Exception	If $pathname does not exist
	 *
	 * @return	Cranberry\Filesystem\Node
	 */
	static public function createNodeFromPathname( string $pathname ) : Node
	{
		if( !is_link( $pathname ) && !file_exists( $pathname ) )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_CREATENODE, $pathname, 'No such path' );
			$exceptionCode = self::ERROR_CODE_NOSUCHNODE;

			throw new Exception( $exceptionMessage, $exceptionCode );
		}

		if( is_link( $pathname ) )
		{
			$node = new Link( $pathname );
		}
		else
		{
			if( is_dir( $pathname ) )
			{
				$node = new Directory( $pathname );
			}
			if( is_file( $pathname ) )
			{
				$node = new File( $pathname );
			}
		}

		return $node;
	}

	/**
	 * Deletes node
	 *
	 * @throws	Cranberry\Filesystem\Exception	If not deletable
	 *
	 * @return	bool
	 */
	abstract public function delete() : bool;

	/**
	 * @return	boolean
	 */
	public function exists()
	{
		return file_exists( $this->getPathname() );
	}

	/**
	 * Return a Directory object representing the current object's parent
	 *   directory.
	 *
	 * If $this represents root (i.e., '/'), return false
	 *
	 * @return	Cranberry\Filesystem\Directory|false
	 */
	public function getParent()
	{
		$selfPathname = $this->getPathname();
		$parentPathname = dirname( $selfPathname );

		if( $parentPathname == $selfPathname )
		{
			return false;
		}

		return new Directory( $parentPathname );
	}

	/**
	 * Returns whether node can be deleted
	 *
	 * @return	bool
	 */
	public function isDeletable() : bool
	{
		if( !$this->exists() )
		{
			return false;
		}

		$parentDirectory = $this->getParent();

		return $parentDirectory->isWritable() && $parentDirectory->isExecutable();
	}

	/**
	 * Attempt to move the file represented by self to $targetNode
	 *
	 * If $targetNode is a Directory object *and* $targetNode exists, the
	 * resulting Node will be a child of $targetNode (i.e., match the
	 * command-line behavior of `mv <source> <directory>`)
	 *
	 * @param   Cranberry\Filesystem\Node   $targetNode
	 *
	 * @throws	Cranberry\Filesystem\Exception	If source node does not exist
	 *
	 * @throws	Cranberry\Filesystem\Exception	If target node is not writable
	 *
	 * @throws	Cranberry\Filesystem\Exception	If target parent does not exist
	 *
	 * @return  Node
	 */
	public function moveTo( Node $targetNode )
	{
		if( !$this->exists() )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_MOVE, $this->getPathname(), 'No such file or directory' );
			throw new Exception( $exceptionMessage, self::ERROR_CODE_NOSUCHNODE );
		}

		if( $targetNode->exists() )
		{
			if( !$targetNode->isWritable() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_MOVETO, $this->getPathname(), $targetNode->getPathname(), 'Permission denied' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
			}
		}
		else
		{
			$targetNodeParent = $targetNode->getParent();

			if( $targetNodeParent == false || !$targetNodeParent->exists() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_MOVETO, $this->getPathname(), $targetNode->getPathname(), 'No such file or directory' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_NOSUCHNODE );
			}

			if( !$targetNodeParent->isWritable() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_MOVETO, $this->getPathname(), $targetNode->getPathname(), 'Permission denied' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
			}
		}

		$newNode = $targetNode;

		if( $targetNode instanceof Directory )
		{
			if( $targetNode->exists() )
			{
				switch( get_class( $this ) )
				{
					case Directory::class:
						$childType = self::DIRECTORY;
						break;

					case File::class:
					default:
						$childType = self::FILE;
						break;
				}

				$newNode = $targetNode->getChild( $this->getBasename(), $childType );
			}
		}

		$didRename = rename( $this->getPathname(), $newNode->getPathname() );

		return $newNode;
	}

	/**
	 * @param   int    $mode
	 * @return  boolean
	 */
	public function setPerms( $mode )
	{
		return chmod( $this->getPathname(), $mode );
	}
}
