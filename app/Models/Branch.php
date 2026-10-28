<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = ['name', 'contact_person', 'address', 'phone', 'email'];

    public function transfers(): HasMany
    {
        return $this->hasMany(Transfer::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(BranchPayment::class);
    }

    // What the branch still owes: everything invoiced minus everything paid
    public function balanceDue(): float
    {
        return round((float) $this->transfers()->sum('total_value') - (float) $this->payments()->sum('amount'), 2);
    }
}
