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
     * @param   boolean $recursive
     * @param   int     $time
     * @param   int     $atime
     * @return  boolean
     */
    public function create( $recursive=false, $time=null, $atime=null )
    {
        if( !$this->getParent()->exists() )
        {
            if( $recursive )
            {
                $this->getParent()->create();
            }
            else
            {
                $exceptionMessage = sprintf( 'Cannot create file %s: Parent does not exist: %s', $this->getBasename(), $this->getParent()->getPathname() );
                throw new \InvalidArgumentException( $exceptionMessage );
            }
        }

        return touch( $this->getPathname(), $time, $atime );
    }

    /**
     * @param    resource    $context
     * @return   boolean
     */
    public function delete( resource $context=null )
    {
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
	 * @param   boolean    $use_include_path
	 * @param   resource   $context
	 * @param   int        $offset
	 * @param   int        $maxlen
	 * @return  string
	 */
	public function getContents( $use_include_path=false, resource $context=null, $offset=0, $maxlen=null )
	{
	    if( !$this->exists() )
	    {
	        $exceptionMessage = sprintf( self::ERROR_STRING_GETCONTENTS, $this->getPathname(), 'No such file' );
	        throw new \InvalidArgumentException( $exceptionMessage, self::ERROR_CODE_NOSUCHNODE );
	    }

		if( !$this->isReadable() )
		{
	        $exceptionMessage = sprintf( self::ERROR_STRING_GETCONTENTS, $this->getPathname(), 'Permission denied' );
	        throw new \InvalidArgumentException( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
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
     * Perform File-specific validation on move request before handing off to
     *   parent method
     *
     * @param   Cranberry\Filesystem\Node   $targetNode
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
				$exceptionMessage = sprintf( 'Cannot move %s to %s: Permission denied.', $this->getPathname(), $targetParentNode->getPathname() );
				throw new \InvalidArgumentException( $exceptionMessage, self::ERROR_CODE_PERMISSIONS );
			}
		}
		if( $targetNode instanceof Directory )
		{
			if( !$targetNode->exists() )
			{
				$exceptionMessage = sprintf( 'Cannot move %s to %s: No such file or directory.', $this->getPathname(), $targetNode->getPathname() );
			    throw new \InvalidArgumentException( $exceptionMessage, self::ERROR_CODE_NOSUCHNODE );
			}
		}

        return parent::moveTo( $targetNode );
    }

    /**
     * @param   mixed       $data
     * @param   int         $flags
     * @param   resource    $flags
     * @return  int|false
     */
    public function putContents( $data, $flags=0, resource $context=null )
    {
        if( $this->exists() )
        {
            if( !$this->isWritable() )
            {
                $exceptionMessage = sprintf( self::ERROR_STRING_PUTCONTENTS, $this->getPathname(), 'Permission denied' );
                throw new \InvalidArgumentException( $exceptionMessage );
            }
        }
        else
        {
            if( !$this->getParent()->isWritable() )
            {
                $exceptionMessage = sprintf( self::ERROR_STRING_PUTCONTENTS, $this->getParent()->getPathname(), 'Permission denied' );
                throw new \InvalidArgumentException( $exceptionMessage );
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
