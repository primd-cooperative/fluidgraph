<?php

namespace FluidGraph\Migrations;

/**
 * Configuration class for graph migrations
 */
class Configuration
{
	/**
	 * The base path in which migrations are located
	 */
	private string $path;

	/**
	 * The namespace in which migrations should be generated
	 */
	private string $namespace;


	/**
	 * Constructor
	 */
	public function __construct(?string $path = null, ?string $namespace = null)
	{
		$this->path = $path ?: getcwd() . '/database/graph/migrations';
		$this->namespace = $namespace ?: 'Migrations\\Graph';
	}

	/**
	 * Get the migrations directory path
	 */
	public function getPath(): string
	{
		return $this->path;
	}

	/**
	 * Set the migrations directory path
	 */
	public function setPath(string $path): self
	{
		$this->path = $path;
		return $this;
	}

	/**
	 * Get the migrations namespace
	 */
	public function getNamespace(): string
	{
		return $this->namespace;
	}

	/**
	 * Set the migrations namespace
	 */
	public function setNamespace(string $namespace): self
	{
		$this->namespace = $namespace;
		return $this;
	}
}
