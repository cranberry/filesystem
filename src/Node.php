<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

abstract class Node extends \SplFileInfo
{
	const DIRECTORY = 1;
	const FILE = 2;

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

    abstract public function create();

    abstract public function delete();

	/**
	 * @return	boolean
	 */
	public function exists()
	{
		return file_exists( $this->getPathname() );
	}

	/**
	 * @return	Cranberry\Filesystem\Directory
	 */
	public function getParent()
	{
		return new Directory( dirname( $this->getPathname() ) );
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
