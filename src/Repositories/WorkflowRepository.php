<?php
namespace Modules\Glitter\Workflow\Repositories;
use App\Search\KeywordSearch;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Modules\Glitter\Workflow\Enums\TriggerKey;
use Modules\Glitter\Workflow\Models\Workflow;
class WorkflowRepository implements WorkflowRepositoryInterface {
 public function paginate(array $filters=[],int $perPage=20): LengthAwarePaginator { $query=Workflow::query()->with(['creator','updater']); if(($filters['status']??null)==='active')$query->where('is_active',true); if(($filters['status']??null)==='inactive')$query->where('is_active',false); if(isset($filters['trigger_key']))$query->where('trigger_key',$filters['trigger_key']); if(($filters['search']??'')!=='')KeywordSearch::applyAny($query,['name','uuid'],(string)$filters['search']); return $query->latest('created_at')->orderByDesc('id')->paginate(min(max($perPage,1),100)); }
 public function findByUuid(string $uuid): ?Workflow { return Workflow::query()->with(['creator','updater'])->where('uuid',$uuid)->first(); }
 public function hasEnabledForTrigger(TriggerKey $triggerKey): bool { return Workflow::query()->where('trigger_key',$triggerKey->value)->where('is_active',true)->exists(); }
 public function create(array $data): Workflow { return Workflow::query()->create($data); }
 public function update(Workflow $workflow,array $data,?int $version=null): bool { $q=$workflow->newQuery()->whereKey($workflow->getKey()); if($version!==null)$q->where('version',$version); return $q->update($data)>0; }
 public function delete(Workflow $workflow): bool { return (bool)$workflow->delete(); }
}
