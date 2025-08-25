<?php

namespace FluidGraph;

/**
 * Abstract base class for graph migrations
 */
abstract class Migration
{
    /**
     * Get the migration description
     */
    abstract public function getDescription(): string;

    /**
     * Run the migration up
     */
    abstract public function up(Graph $graph): void;

    /**
     * Run the migration down (rollback)
     */
    abstract public function down(Graph $graph): void;
}
