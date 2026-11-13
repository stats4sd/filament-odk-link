<?php

namespace Stats4sd\FilamentOdkLink\Exceptions;

use Exception;

/**
 * Thrown when a queued "attach the generated XLSForm file" job finds the file already taken by an identical job
 * that ran just before it. The form already has its new file, so this is not an error worth telling the user about.
 */
class XlsformFileSupersededException extends Exception {}
