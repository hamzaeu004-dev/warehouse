<?php

namespace App\Livewire;

use App\Models\Branch;
use App\Services\ActivityLogger;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Branches')]
class Branches extends Component
{
    public string $newName = '';

    public ?int $editingId = null;

    public string $editName = '';

    public string $editContact = '';

    public string $editAddress = '';

    public string $editPhone = '';

    public function add(): void
    {
        $this->validate(['newName' => ['required', 'string', 'max:100', 'unique:branches,name']], [], ['newName' => 'branch name']);
        $branch = Branch::create(['name' => trim($this->newName)]);
        ActivityLogger::record('branch.created', "Branch added: {$branch->name}", $branch);
        $this->reset('newName');
    }

    public function edit(int $id): void
    {
        $branch = Branch::findOrFail($id);
        $this->editingId = $id;
        $this->editName = $branch->name;
        $this->editContact = (string) $branch->contact_person;
        $this->editAddress = (string) $branch->address;
        $this->editPhone = (string) $branch->phone;
    }

    public function update(): void
    {
        $this->validate([
            'editName' => ['required', 'string', 'max:100', Rule::unique('branches', 'name')->ignore($this->editingId)],
            'editContact' => ['nullable', 'string', 'max:100'],
            'editAddress' => ['nullable', 'string', 'max:255'],
            'editPhone' => ['nullable', 'string', 'max:30'],
        ], [], ['editName' => 'branch name', 'editContact' => 'contact person', 'editAddress' => 'address', 'editPhone' => 'phone']);

        $branch = Branch::findOrFail($this->editingId);
        $before = $branch->only(['name', 'contact_person', 'address', 'phone']);
        $branch->update([
            'name' => trim($this->editName),
            'contact_person' => trim($this->editContact) ?: null,
            'address' => trim($this->editAddress) ?: null,
            'phone' => trim($this->editPhone) ?: null,
        ]);

        $changes = [];
        foreach (['name' => 'name', 'contact_person' => 'contact person', 'address' => 'address', 'phone' => 'phone'] as $field => $label) {
            if ($before[$field] !== $branch->{$field}) {
                $changes[] = "{$label} ".($before[$field] ?? 'none').' → '.($branch->{$field} ?? 'none');
            }
        }
        if ($before['name'] !== $branch->name && count($changes) === 1) {
            ActivityLogger::record('branch.updated', "Branch renamed: {$before['name']} → {$branch->name}", $branch);
        } elseif ($changes) {
            ActivityLogger::record('branch.updated', "Branch updated: {$before['name']}. ".implode(', ', $changes), $branch);
        }

        $this->reset('editingId', 'editName', 'editContact', 'editAddress', 'editPhone');
    }

    public function delete(int $id): void
    {
        $branch = Branch::withCount(['transfers', 'payments'])->findOrFail($id);
        if ($branch->transfers_count > 0 || $branch->payments_count > 0) {
            $this->addError('delete', "Stock has been sent to {$branch->name}, so it cannot be deleted.");

            return;
        }
        ActivityLogger::record('branch.deleted', "Branch deleted: {$branch->name}", $branch);
        $branch->delete();
    }

    public function render()
    {
        return view('livewire.branches', [
            'branches' => Branch::withCount('transfers')->withSum('transfers', 'total_value')->orderBy('id')->get(),
        ]);
    }
}
