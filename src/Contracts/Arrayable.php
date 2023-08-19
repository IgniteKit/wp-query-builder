<?php

namespace IgniteKit\WP\QueryBuilder\Contracts;

/**
 * Interface for any object that can be casted to array.
 *
 * @copyright 10 Quality <info@10quality.com>
 * @copyright Darko G <dg@darkog.com>
 * @license MIT
 * @version 1.0.0
 */
interface Arrayable
{
    /**
     * Returns object as string.
     * @since 1.0.0
     */
    public function __toArray();
    /**
     * Returns object as string.
     * @since 1.0.0
     */
    public function toArray();
}