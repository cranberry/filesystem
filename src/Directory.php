<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

class Directory extends Node
{
	const ERROR_STRING_CREATE = 'Cannot create %s: %s.';
	const ERROR_STRING_GETCHILDREN = 'Cannot retrieve children of %s: %s.';

	/**
	 * @var    DirectoryIterator
	 */
	protected $directoryIterator;

	/**
	 * Attempt to create the directory
	 *
	 * If $recursive is true, no exception will be thrown if the directory
	 * already exists (i.e., match the command-line behavior of
	 * `mkdir -p <dir>`)
	 *
	 * @param   boolean    $recursive
	 *
	 * @param   int        $mode
	 *
	 * @param   resource   $context
	 *
	 * @throws	Cranberry\Filesystem\Exception	If attempting to create existing directory non-recursively
	 *
	 * @throws	Cranberry\Filesystem\Exception	If parent directory is unwritable
	 *
	 * @throws	Cranberry\Filesystem\Exception	If parent directory does not exist and not creating recursively
	 *
	 * @return  boolean
	 */
	public function create( $recursive=false, $mode=0777, resource $context=null )
	{
		if( $this->exists() )
		{
			if( $recursive )
			{
				return true;
			}
			else
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_CREATE, $this->getBasename(), 'Directory exists' );
				throw new Exception( $exceptionMessage, self::ERROR_CODE_NODEEXISTS );
			}
		}
		else
		{
			$parentDirectory = $this->getParent();

			if( $parentDirectory->exists() )
			{
				if( !$parentDirectory->isWritable() )
				{
					$exceptionMessage = sprintf( self::ERROR_STRING_CREATE, $this->getBasename(), 'Permission denied' );
					throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
				}
			}
			else
			{
				if( !$recursive )
				{
					$exceptionMessage = sprintf( self::ERROR_STRING_CREATE, $this->getBasename(), 'Permission denied' );
					throw new Exception( $exceptionMessage, self::ERROR_CODE_INVALIDTARGET );
				}
			}
		}

		if( $context == null )
		{
			$result = mkdir( $this->getPathname(), $mode, $recursive );
		}
		else
		{
			$result = mkdir( $this->getPathname(), $mode, $recursive, $context );
		}

		return $result;
	}

	/**
	 * Deletes directory
	 *
	 * @throws	Cranberry\Filesystem\Exception	If not deletable
	 *
	 * @return   boolean
	 */
	public function delete() : bool
	{
		if( !$this->isDeletable() )
		{
			if( !$this->exists() )
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_DELETE, $this->getPathname(), 'No such directory' );
				$exceptionCode = self::ERROR_CODE_NOSUCHNODE;
			}
			else
			{
				$exceptionMessage = sprintf( self::ERROR_STRING_DELETE, $this->getPathname(), 'Permission denied' );
				$exceptionCode = self::ERROR_CODE_PERMISSIONS;
			}

			throw new Exception( $exceptionMessage, $exceptionCode );
		}

		$children = $this->getChildren();

		if( count( $children ) > 0 )
		{
			foreach( $children as $child )
			{
				$child->delete();
			}
		}

		return rmdir( $this->getPathname() );
	}

	/**
	 * Returns Node object representing child of current directory
	 *
	 * @param	string	$filename
	 *
	 * @return	Cranberry\Filesystem\Node
	 */
	public function getChild( $filename, $type=null ) : Node
	{
		$childPathname = $this->getPathname() . DIRECTORY_SEPARATOR . $filename;

		/* If $filename exists on disk in any form */
		if( is_link( $childPathname ) || file_exists( $childPathname ) )
		{
			if( is_link( $childPathname ) )
			{
				$childClass = Link::class;
			}
			else
			{
				if( is_dir( $childPathname ) )
				{
					$childClass = Directory::class;
				}
				if( is_file( $childPathname ) )
				{
					$childClass = File::class;
				}
			}
		}
		/* If $filename does not exist on disk, $type is required */
		else
		{
			switch( $type )
			{
				case null:
					throw new \BadMethodCallException( 'Missing child node type' );
					break;

				case self::DIRECTORY:
					$childClass = Directory::class;
					break;

				case self::FILE:
					$childClass = File::class;
					break;

				case self::LINK:
					$childClass = Link::class;
					break;
			}
		}

		return new $childClass( $childPathname );
	}

	/**
	 * Return an array of Cranberry\Filesystem\Node objects representing each
	 * child file
	 *
	 * @param	callable    $filter
	 *
	 * @throws	Cranberry\Filesystem\Exception	If directory does not exist
	 *
	 * @throws	Cranberry\Filesystem\Exception	If directory is unreadable
	 *
	 * @return	array
	 */
	public function getChildren( callable $filter=null, array $filterArguments=[] )
	{
		if( !$this->exists() )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_GETCHILDREN, $this->getPathname(), 'No such directory'  );
			throw new Exception( $exceptionMessage, self::ERROR_CODE_NOSUCHNODE );
		}

		if( !$this->isReadable() )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_GETCHILDREN, $this->getPathname(), 'Permission denied'  );
			throw new Exception( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
		}

		$children = [];

		if( $this->directoryIterator == null )
		{
			$this->directoryIterator = new \DirectoryIterator( $this->getPathname() );
		}

		/* Filter */
		if( $filter != null )
		{
			$directoryIterator = new DirectoryFilterIterator( $this->directoryIterator );

			$directoryIterator->setAccept( $filter );
			$directoryIterator->setArguments( $filterArguments );
		}
		else
		{
			$directoryIterator = $this->directoryIterator;
		}

		foreach( $directoryIterator as $splFileInfo )
		{
			if( $splFileInfo->isDot() )
			{
				continue;
			}

			$pathname = $splFileInfo->getPathname();
			$child = new File( $pathname );

			if( $splFileInfo->isDir() )
			{
				$child = new Directory( $pathname );
			}
			if( $splFileInfo->isLink() )
			{
				$child = new Link( $pathname );
			}

			$children[] = $child;
		}

		return $children;
	}

	/**
	 * A convenience method for filtering children by file extension
	 *
	 * @param   array    $extensions
	 * @return  array
	 */
	public function getChildrenByFileExtension( array $extensions )
	{
		$filter = function( $extensions )
		{
			$node = $this->current();
			return in_array( $node->getExtension(), $extensions );
		};

		return $this->getChildren( $filter, [$extensions] );
	}

	/**
	 * Returns whether directory can be deleted
	 *
	 * @return	bool
	 */
	public function isDeletable() : bool
	{
		if( !parent::isDeletable() )
		{
			return false;
		}

		$children = $this->getChildren();
		foreach( $children as $childNode )
		{
			if( !$childNode->isDeletable() )
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * @param   Cranberry\Filesystem\Node  $node
	 * @return  boolean
	 */
	public function isParentOfNode( Node $node )
	{
		do
		{
			$parentNode = $node->getParent();

			/* $node has no parent (i.e., '/') */
			if( $parentNode == false )
			{
				return false;
			}

			if( $parentNode->getPathname() == $this->getPathname() )
			{
				return true;
			}

			$node = $parentNode;
		}
		while( $parentNode != false );

		return false;
	}

	/**
	 * Perform File-specific validation on move request before handing off to
	 * parent method
	 *
	 * @param   Cranberry\Filesystem\Directory  $targetDirectory
	 *
	 * @throws	Cranberry\Filesystem\Exception	If target node is not an instance of Cranberry\Filesystem\Directory
	 *
	 * @return  boolean
	 */
	public function moveTo( Node $targetDirectory )
	{
		/* As on the command line, a Directory object can only be moved to another Directory */
		if( !($targetDirectory instanceof Directory) )
		{
			$exceptionMessage = sprintf( self::ERROR_STRING_MOVETO, $this->getPathname(), $targetDirectory->getPathname(), 'Invalid destination' );
			throw new Exception( $exceptionMessage, self::ERROR_CODE_INVALIDTARGET );
		}

		/* Prevent attempts to move a parent directory into one of its children */
		if( $this->isParentOfNode( $targetDirectory ) )
		{
			$exceptionMessage = sprintf( 'Cannot move %s to child directory %s.', $this->getPathname(), $targetDirectory->getPathname() );
			throw new Exception( $exceptionMessage, self::ERROR_CODE_INVALIDTARGET );
		}

		return parent::moveTo( $targetDirectory );
	}
}
