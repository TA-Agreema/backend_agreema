<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;

class ContractPolicy
{
    public function view(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract)
            || $this->isAssignedInternalSigner($user, $contract);
    }

    public function update(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract);
    }

    public function submit(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract);
    }

    public function createAddendum(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract);
    }

    public function deleteAddendum(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract);
    }

    public function createTermination(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract);
    }

    public function deleteTermination(User $user, Contract $contract): bool
    {
        return $this->isAdmin($user)
            || $this->isCreator($user, $contract);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    private function isCreator(User $user, Contract $contract): bool
    {
        return (int) $contract->created_by === (int) $user->id;
    }

    private function isAssignedInternalSigner(User $user, Contract $contract): bool
    {
        return $contract->signers()
            ->where('signer_type', 'internal')
            ->where('user_id', $user->id)
            ->exists();
    }
}
