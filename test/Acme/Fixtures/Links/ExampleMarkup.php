<?php

namespace Acme\Fixtures\Links;

/**
 * Its examples write HTML, the same markup the generator uses for type cells.
 *
 * @example
 * echo '<em>\Acme\Fixtures\Links\Bar</em>';
 * *This class extends \Acme\Fixtures\Links\Bar*
 */
class ExampleMarkup
{
    /**
     * Renders a type name.
     *
     * @example
     * echo '<em>\DateTime</em>';
     */
    public function render(): Bar
    {
        return new BarBaz();
    }
}
