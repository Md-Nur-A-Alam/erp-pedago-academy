<?php

namespace App\Actions;

use App\Models\Employee;
use App\Models\Team;
use App\Notifications\AppNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * Sends in-app notifications to employees. Everything that needs to tell someone something goes through here.
 */
class Notifier
{
    /**
     * @param  Employee|iterable<Employee>|null  $recipients
     * @param  string|null  $key  Groups related notices so they can be cleared together, for example `followup:12`.
     * @param  bool  $once  Skip when a notice with the same key and kind was already sent to that person.
     */
    public function send(Employee|iterable|null $recipients, string $kind, string $title, string $body, ?string $url = null, string $tone = 'blue', ?string $key = null, bool $once = false): void
    {
        if ($recipients === null) {
            return;
        }

        $people = $recipients instanceof Employee ? [$recipients] : $recipients;
        $sent = [];

        foreach ($people as $person) {
            $user = $person->user;

            if ($user === null || $person->status !== 'Active' || isset($sent[$person->id])) {
                continue;
            }
            $sent[$person->id] = true;

            if ($once && $key !== null && $this->alreadySent($person, $kind, $key)) {
                continue;
            }

            $user->notify(new AppNotification($kind, $title, $body, $url, $tone, $key));
        }
    }

    /**
     * Mark every unread notice with this key as read, because what it asked for has been done.
     */
    public function resolve(string $key): void
    {
        DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('data', 'like', '%'.$this->keyFragment($key).'%')
            ->update(['read_at' => now()]);
    }

    /**
     * Administrators and developers.
     *
     * @return Collection<int, Employee>
     */
    public function admins(): Collection
    {
        return Employee::query()->with('user')->whereIn('role', ['Admin', 'Developer'])->where('status', 'Active')->get();
    }

    /**
     * Active people with a role, such as everyone in Accounts.
     *
     * @return Collection<int, Employee>
     */
    public function withRole(string $role): Collection
    {
        return Employee::query()->with('user')->where('role', $role)->where('status', 'Active')->get();
    }

    /**
     * The people who manage a sales person: the leader of their team, the supervisors of it, and administrators.
     *
     * @return Collection<int, Employee>
     */
    public function managersOf(Employee $person, bool $withAdmins = true): Collection
    {
        $team = $person->effectiveTeam();
        $managers = collect();

        if ($team !== null) {
            $managers = $managers
                ->merge(Employee::query()->with('user')->whereKey($team->team_leader_id)->get())
                ->merge($this->supervisorsOf($team));
        }

        if ($withAdmins) {
            $managers = $managers->merge($this->admins());
        }

        return $managers->reject(fn (Employee $manager): bool => $manager->id === $person->id)->unique('id')->values();
    }

    /**
     * @return Collection<int, Employee>
     */
    public function supervisorsOf(Team $team): Collection
    {
        return $team->supervisors()->with('user')->get();
    }

    private function alreadySent(Employee $person, string $kind, string $key): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_id', $person->user_id)
            ->where('data', 'like', '%'.$this->keyFragment($key).'%')
            ->where('data', 'like', '%'.str_replace('"', '', json_encode($kind)).'%')
            ->exists();
    }

    private function keyFragment(string $key): string
    {
        return '"key":'.json_encode($key).'';
    }
}
