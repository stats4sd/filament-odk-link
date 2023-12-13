<?php

namespace Stats4sd\FilamentOdkLink\Filament\Resources;

use Filament\Forms;
use Filament\Tables;
use Filament\Forms\Form;
use Filament\Tables\Table;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use App\Models\MentalHealthAlert;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\Mail;
use App\Mail\MentalHealthFollowUpRequired;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Components\ImageEntry;
use App\Mail\MaleMentalHealthFollowUpRequired;
use Stats4sd\FilamentOdkLink\Models\OdkLink\Submission;
use Stats4sd\FilamentOdkLink\Filament\Resources\TeamResource\Pages;

class TeamResource extends Resource
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    // get custom team model
    public static function getModel(): string
    {
        return config('filament-odk-link.models.team_model');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Team Details')
                    ->schema([

                        Forms\Components\TextInput::make('name')
                            ->required(),
                        Forms\Components\Textarea::make('description'),
                        Forms\Components\FileUpload::make('avatar'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        //££
        logger("TeamResource.table()");

        $submission = Submission::find(102);
        
        // find dataset ID of main survey template section
        $mainSurveyTemplateSections = $submission->xlsformVersion->xlsform->xlsformTemplate->xlsformTemplateSections->where('is_repeat', 0);
        // dump($mainSurveyTemplateSections);

        foreach ($mainSurveyTemplateSections as $mainSurveyTemplateSection) {
            $mainSurveyDatasetId = $mainSurveyTemplateSection->dataset_id;
        }
        // dump($mainSurveyDatasetId);

        // find main survey entity
        $mainSurveyEntities = $submission->entities->where('dataset_id', $mainSurveyDatasetId);
        // dump($mainSurveyEntities);

        foreach ($mainSurveyEntities as $mainSurveyEntity) {
            // dump($mainSurveyEntity->id);

            $indPhq9HurtThoughts = TeamResource::getEntityValue($mainSurveyEntity->values, 'ind_phq9_hurt_thoughts');

            if ($indPhq9HurtThoughts > 0) {
                // need to send mental health email alert

                // get the required data for email alert
                $hhRespSex = TeamResource::getEntityValue($mainSurveyEntity->values, 'hh_resp_sex');

                $hhRespName = TeamResource::getEntityValue($mainSurveyEntity->values, 'hh_resp_name');
                $sumPhq9 = TeamResource::getEntityValue($mainSurveyEntity->values, 'sum_phq9');
                $start = TeamResource::getEntityValue($mainSurveyEntity->values, 'start');
                $end = TeamResource::getEntityValue($mainSurveyEntity->values, 'end');

                $subDistId = TeamResource::getEntityValue($mainSurveyEntity->values, 'sub_dist_id');
                $villageId = TeamResource::getEntityValue($mainSurveyEntity->values, 'village_id');
                $fkey = TeamResource::getEntityValue($mainSurveyEntity->values, 'fkey');

                $interviewerId = TeamResource::getEntityValue($mainSurveyEntity->values, 'interviewer_id');
                $supervisorId = TeamResource::getEntityValue($mainSurveyEntity->values, 'supervisor_id');
                $hhList = TeamResource::getEntityValue($mainSurveyEntity->values, 'hh_list');
                $migrantList = TeamResource::getEntityValue($mainSurveyEntity->values, 'migrant_list');

                $followUpConsent = TeamResource::getEntityValue($mainSurveyEntity->values, 'follow_up_consent');
                $contactPhoneNumber = TeamResource::getEntityValue($mainSurveyEntity->values, 'contact_phone_number');
                $contactBestTime = TeamResource::getEntityValue($mainSurveyEntity->values, 'contact_best_time');
                $contactAddress = TeamResource::getEntityValue($mainSurveyEntity->values, 'contact_address');

                // dump($indPhq9HurtThoughts);
                // dump($hhRespSex);

                // dump($hhRespName);
                // dump($sumPhq9);
                // dump($start);
                // dump($end);

                // dump($subDistId);
                // dump($villageId);
                // dump($fkey);

                // dump($interviewerId);
                // dump($supervisorId);
                // dump($hhList);
                // dump($migrantList);

                // dump($followUpConsent);
                // dump($contactPhoneNumber);
                // dump($contactBestTime);
                // dump($contactAddress);

                // create a new record for mental health alert
                $mentalHealthAlert = MentalHealthAlert::create([
                    'hh_resp_name' => $hhRespName,
                    'hh_resp_sex' => $hhRespSex,

                    'ind_phq9_hurt_thoughts' => $indPhq9HurtThoughts,
                    'sum_phq9' => $sumPhq9,
                    'start' => $start,
                    'end' => $end,

                    'sub_dist_id' => $subDistId,
                    'village_id' => $villageId,
                    'fkey' => $fkey,

                    'interviewer_id' => $interviewerId,
                    'supervisor_id' => $supervisorId,
                    'hh_list' => $hhList,
                    'migrant_list' => $migrantList,

                    'follow_up_consent' => $followUpConsent,
                    'contact_phone_number' => $contactPhoneNumber,
                    'contact_best_time' => $contactBestTime,
                    'contact_address' => $contactAddress,
                ]);

                // send mental health alert email to multiple recipients by sending a separate email to each recipient individually
                $recipients = config('mail.mental_health_alert_recipients');
                $recipientsArray = explode (",", $recipients); 

                foreach ($recipientsArray as $recipient) {
                    Mail::to($recipient)->send(new MentalHealthFollowUpRequired($mentalHealthAlert));
                }

            }
        }

        //££

        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar'),
                Tables\Columns\TextColumn::make('name'),
                Tables\Columns\TextColumn::make('xlsform_count')
                    ->label('Xlsforms')
                    ->counts('xlsforms'),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }


    public static function getEntityValue($entityValues, $datasetVariableId) {
        $result = '';

        // find the coresponding value for a provided key
        $values = $entityValues->where('dataset_variable_id', $datasetVariableId)->pluck('value');

        // suppose there should be one record, or there is no record
        foreach ($values as $value) {
            $result = $value;
        }

        return $result;
    }


    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Team Details')
                    ->columns(6)
                    ->schema([
                        ImageEntry::make('avatar')
                            ->label('')
                            ->columnSpan(2),
                        TextEntry::make('description')
                            ->getStateUsing(fn ($record) => new HtmlString(preg_replace('/\n/', '<br/>', $record->description)))
                            ->columnSpan(4),
                        ViewEntry::make('qr_code')
                            ->view('filament-odk-link::filament.infolists.components.team-qr-code'),

                    ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            TeamResource\RelationManagers\XlsformsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTeams::route('/'),
            'create' => Pages\CreateTeam::route('/create'),
            'edit' => Pages\EditTeam::route('/{record}/edit'),
            'view' => Pages\ViewTeam::route('/{record}'),
        ];
    }
}
