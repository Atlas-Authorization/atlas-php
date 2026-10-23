<?php

declare(strict_types=1);

namespace Atlas\Verify;

/** Atlas's introspection endpoint could not be reached, or answered with a non-2xx status. */
final class IntrospectionException extends \RuntimeException
{
}
