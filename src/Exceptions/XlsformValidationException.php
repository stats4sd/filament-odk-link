<?php

namespace Stats4sd\FilamentOdkLink\Exceptions;

use Exception;

/**
 * Thrown when ODK Central rejects the uploaded XLSForm file itself. Retrying
 * will never help — the form definition has to be fixed.
 */
class XlsformValidationException extends Exception {}
