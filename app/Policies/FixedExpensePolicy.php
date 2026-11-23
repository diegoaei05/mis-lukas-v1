<?php

namespace App\Policies;

use App\Models\FixedExpense;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class FixedExpensePolicy
{
    /**
     * Determine whether the user can edit the bill.
     */
    public function update(User $user, FixedExpense $fixedExpense): Response
    {
        return $user->id === $fixedExpense->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can delete the bill.
     */
    public function delete(User $user, FixedExpense $fixedExpense): Response
    {
        return $user->id === $fixedExpense->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can mark or clear a payment on the bill.
     */
    public function pay(User $user, FixedExpense $fixedExpense): Response
    {
        return $user->id === $fixedExpense->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
