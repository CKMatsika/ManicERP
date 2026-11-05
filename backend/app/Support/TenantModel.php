<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Base class for every council operational model. The `tenant` connection is
 * pointed at the resolved council database by the TenantManager and is always
 * re-validated against the council directory before use.
 */
abstract class TenantModel extends Model
{
    use HasUlids;

    protected $connection = 'tenant';

    protected $keyType = 'string';

    public $incrementing = false;

    public function getConnectionName(): string
    {
        return 'tenant';
    }
}
