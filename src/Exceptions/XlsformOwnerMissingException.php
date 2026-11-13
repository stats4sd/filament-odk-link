<?php

namespace Stats4sd\FilamentOdkLink\Exceptions;

use Exception;

/**
 * Thrown when a queued job needs a form's owner (e.g. the team) but it no longer exists,
 * usually because the team was deleted while the job was waiting in the queue.
 * Retrying will never help, and there is nobody left who needs to hear about it.
 */
class XlsformOwnerMissingException extends Exception {}
