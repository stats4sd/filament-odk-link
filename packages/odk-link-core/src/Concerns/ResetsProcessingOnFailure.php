<?php

namespace Stats4sd\FilamentOdkLink\Concerns;

use Stats4sd\FilamentOdkLink\Models\OdkLink\XlsformTemplate;
use Throwable;

/**
 * Failure hook for the template-import chain jobs. Each job holds the model being
 * imported as a public $model property (XlsformTemplate or XlsformModuleVersion);
 * on failure the `processing` flag is cleared so the template is not stuck, and
 * Super Admins get a durable notification.
 */
trait ResetsProcessingOnFailure
{
    use NotifiesOnJobFailure;

    public function failed(?Throwable $exception = null): void
    {
        $this->model->updateQuietly(['processing' => false]);

        $title = $this->model instanceof XlsformTemplate
            ? "Xlsform template import failed: {$this->model->title}"
            : "Module questions import failed: {$this->model->name}";

        $this->notifyJobFailure($title, $exception, $this->superAdmins());
    }
}
