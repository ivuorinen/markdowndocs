<?php

declare(strict_types=1);

// A file whose only class is anonymous, in the shape framework migrations
// use. The scanner must find no type here, and must not load the file.
return new class extends \ArrayObject
{
};
