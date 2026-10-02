<?php

declare(strict_types=1);

namespace DesignQa\Domain\Model;

/**
 * Case transformation applied when rendering (Figma "textCase", CSS "text-transform").
 */
enum TextCase: string
{
    case None = 'none';
    case Upper = 'upper';
    case Lower = 'lower';
    case Title = 'title';
}
