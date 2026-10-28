<?php
namespace Tests\Feature;

use App\Models\User;
use App\Support\ReportDependencies;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class PlanningTaskExportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_creates_a_planning_and_task_then_exports_both_as_real_pdf_and_excel(): void
    {
        Mail::fake();
        Sanctum::actingAs($user=User::factory()->create());
        $monday=now()->startOfWeek()->addWeek()->toDateString();
        $friday=now()->startOfWeek()->addWeek()->addDays(4)->toDateString();
        $event=$this->postJson('/api/v1/activities',[
            'activity_type'=>'reunion','date_activite'=>$monday,'date_fin'=>$monday,
            'heure_debut'=>'09:00','heure_fin'=>'10:00','notes'=>'Réunion de contrôle technique',
        ])->assertCreated()->json('data');
        $planning=$this->postJson('/api/v1/tour-plans',[
            'nom'=>'Planning de contrôle','date_debut'=>$monday,'date_fin'=>$friday,
            'statut'=>'brouillon','activity_ids'=>[$event['id']],
        ])->assertCreated()->json('data');
        $task=$this->postJson('/api/v1/tasks',[
            'title'=>'Tâche de contrôle','description'=>'Vérifier les documents du planning',
            'type'=>'other','status'=>'planned','start_time'=>$monday.' 11:00:00','end_time'=>$monday.' 12:00:00',
        ])->assertCreated()->json();
        $this->assertDatabaseHas('tour_plans',['id'=>$planning['id'],'nom'=>'Planning de contrôle','user_id'=>$user->id]);
        $this->assertDatabaseHas('tasks',['id'=>$task['id'],'title'=>'Tâche de contrôle','user_id'=>$user->id]);
        $this->getJson('/api/v1/tour-plans/'.$planning['id'])->assertOk();
        $this->getJson('/api/v1/tasks/'.$task['id'])->assertOk()->assertJsonPath('title','Tâche de contrôle');
        $data=['title'=>'Vérification planning et tâches','filters'=>[],'summary'=>[],
            'sections'=>[
                ['title'=>'Planning créé par API','columns'=>['Identifiant','Nom'],'rows'=>[[$planning['id'],$planning['nom']]]],
                ['title'=>'Tâche créée par API','columns'=>['Identifiant','Titre','Statut'],'rows'=>[[$task['id'],$task['title'],$task['status']]]],
            ]];
        $folder=sys_get_temp_dir().'/skynity-verified-exports';
        if(!is_dir($folder))mkdir($folder,0775,true);
        foreach(['pdf'=>'%PDF-','xlsx'=>"PK\x03\x04"] as $format=>$signature){
            $response=$this->postJson('/api/v1/reports/document',array_merge($data,['format'=>$format]))->assertOk();
            $this->assertStringStartsWith($signature,$response->getContent());
            file_put_contents($folder.'/planning-tasks.'.$format,$response->getContent());
        }
        $book=IOFactory::load($folder.'/planning-tasks.xlsx');
        $this->assertSame($planning['nom'],$book->getSheet(1)->getCell('B2')->getValue());
        $this->assertSame($task['title'],$book->getSheet(2)->getCell('B2')->getValue());
        $book->disconnectWorksheets();
    }

    public function test_missing_renderers_return_an_actionable_error_instead_of_a_missing_class_failure(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->app->instance(ReportDependencies::class,new class extends ReportDependencies {
            protected function available(string $class): bool {return false;}
        });
        foreach(['pdf','xlsx'] as $format){
            $response=$this->postJson('/api/v1/reports/document',[
                'format'=>$format,'title'=>'Contrôle','filters'=>[],'summary'=>[],
                'sections'=>[['title'=>'Détails','columns'=>['Nom'],'rows'=>[]]],
            ])->assertStatus(503);
            $this->assertStringContainsString('Composer',$response->json('message'));
            $this->assertStringNotContainsString('Class',$response->json('message'));
        }
    }
}
