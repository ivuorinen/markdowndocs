<?php

namespace Acme\Fixtures\Links;

/**
 * Refers to both prefix-sharing names, and to a union of them.
 */
class User
{
    /**
     * @return \Acme\Fixtures\Links\BarBaz
     */
    public function makeBarBaz()
    {
    }

    /**
     * @return \Acme\Fixtures\Links\Bar
     */
    public function makeBar()
    {
    }

    /**
     * @param int|string $either A union that must stay in one table cell
     *
     * @return \Acme\Fixtures\Links\Bar|\Acme\Fixtures\Links\BarBaz
     */
    public function makeEither($either)
    {
    }
}
