<?php

namespace IgniteKit\WP\QueryBuilder\Contracts;

/**
 * Interface for any object that can be casted to JSON.
 *
 * @copyright 10 Quality <info@10quality.com>
 * @copyright Darko G <dg@darkog.com>
 * @license MIT
 * @package IgniteKit\WP\QueryBuilder\Contracts
 * @version 1.0.2
 */
interface JSONable
{
    /**
     * Returns object as JSON string.
     * @since 1.0.2
     */
    public function __toJSON($options = 0, $depth = 512);
    /**
     * Returns object as JSON string.
     * @since 1.0.2
     */
    public function toJSON($options = 0, $depth = 512);
}