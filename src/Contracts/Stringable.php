<?php

namespace IgniteKit\WP\QueryBuilder\Contracts;

/**
 * Interface for any object that can be casted to string.
 *
 * @copyright 10 Quality <info@10quality.com>
 * @copyright Darko G <dg@darkog.com>
 * @license MIT
 * @package IgniteKit\WP\QueryBuilder\Contracts
 * @version 1.0.0
 */
interface Stringable
{
    /**
     * Returns object as string.
     * @since 1.0.0
     */
    public function __toString();
}