<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

class Directory extends Node
{
    const ERROR_STRING_GETCHILDREN = 'Cannot retrieve children of %s: %s.';

    /**
     * @var    DirectoryIterator
     */
    protected $directoryIterator;

    /**
     * @param   boolean    $recursive
     * @param   int        $mode
     * @param   resource   $context
     * @return  boolean
     */
    public function create( $recursive=false, $mode=0777, resource $context=null )
    {
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
     * @return   boolean
     */
    public function delete()
    {
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
	 * @param	string	$filename
	 * @return	Cranberry\Filesystem\Node
	 */
	public function getChild( $filename, $type=self::FILE )
	{
		$childPathname = $this->getPathname() . DIRECTORY_SEPARATOR . $filename;

		switch( $type )
		{
			case self::DIRECTORY:
				$childClass = Directory::class;
				break;

			case null:
			case self::FILE:
				$childClass = File::class;
				break;
		}
		$child = new $childClass( $childPathname );

		return $child;
	}

    /**
     * Return an array of Cranberry\Filesystem\Node objects representing each
     * child file
     *
     * @param	callable    $filter
     * @return	array
     */
    public function getChildren( callable $filter=null, array $filterArguments=[] )
    {
        if( !$this->exists() )
        {
            $exceptionMessage = sprintf( self::ERROR_STRING_GETCHILDREN, $this->getPathname(), 'No such directory'  );
            throw new \InvalidArgumentException( $exceptionMessage );
        }

        if( !$this->isReadable() )
        {
            $exceptionMessage = sprintf( self::ERROR_STRING_GETCHILDREN, $this->getPathname(), 'Permission denied'  );
            throw new \InvalidArgumentException( $exceptionMessage );
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
}
