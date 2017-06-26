<?php

/*
 * This file is part of Cranberry\Filesystem
 */
namespace Cranberry\Filesystem;

class DirectoryFilterIterator extends \FilterIterator
{
	/**
	 * @var    callable
	 */
	protected $acceptCallable;

	/**
	 * @var    array
	 */
	protected $arguments=[];

	/**
	 * @return    boolean
	 */
	public function accept()
	{
		return call_user_func_array( $this->acceptCallable, $this->arguments );
	}

	/**
	 * @return    mixed
	 */
	public function current()
	{
		return $this->getInnerIterator()->current();
	}

	/**
	 * @param    callable    $acceptCallable
	 */
	public function setAccept( callable $acceptCallable )
	{
		$this->acceptCallable = $acceptCallable->bindTo( $this );
	}

	/**
	 * @param    array    $arguments
	 */
	public function setArguments( array $arguments )
	{
		$this->arguments = $arguments;
	}
}
