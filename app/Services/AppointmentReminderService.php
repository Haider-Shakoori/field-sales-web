<?php

namespace App\Services;

use App\Models\Appointment;

class AppointmentReminderService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function sendDue(): int
    {
        $now = now();
        $sent = 0;

        Appointment::query()
            ->with(['assignedSalesman.user', 'customer', 'tenant'])
            ->where('status', 'scheduled')
            ->whereNull('reminder_sent_at')
            ->whereNotNull('reminder_minutes_before')
            ->where('starts_at', '>', $now->copy()->subMinutes(5))
            ->where('starts_at', '<=', $now->copy()->addDay())
            ->orderBy('starts_at')
            ->chunkById(100, function ($appointments) use ($now, &$sent): void {
                foreach ($appointments as $appointment) {
                    $minutes = (int) $appointment->reminder_minutes_before;
                    $dueAt = $appointment->starts_at->copy()->subMinutes($minutes);

                    if ($dueAt->isFuture()) {
                        continue;
                    }

                    $recipient = $appointment->assignedSalesman?->user;

                    if (! $recipient?->is_active) {
                        continue;
                    }

                    $notification = $this->notifications->notify(
                        $recipient,
                        'appointment.reminder',
                        'appointment_reminders',
                        'Upcoming appointment',
                        $appointment->title
                            .' · '
                            .$appointment->starts_at
                                ->copy()
                                ->setTimezone(
                                    $appointment->tenant?->timezone
                                        ?: config('app.timezone', 'UTC'),
                                )
                                ->format('Y-m-d H:i'),
                        [
                            'appointment_id' => $appointment->uuid,
                            'customer_id' => $appointment->customer?->uuid,
                            'customer_name' => $appointment->customer?->name,
                            'starts_at' => $appointment->starts_at?->toISOString(),
                        ],
                    );

                    if ($notification) {
                        $appointment->forceFill([
                            'reminder_sent_at' => now(),
                        ])->save();
                        $sent++;
                    }
                }
            });

        return $sent;
    }
}
