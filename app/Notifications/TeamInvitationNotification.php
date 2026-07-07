<?php

namespace App\Notifications;

use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(public TeamInvitation $invitation) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $team = $this->invitation->team()->with('owner', 'projects')->first();
        $projectNames = $team?->projects->pluck('project_name')->take(3)->all() ?? [];
        $projectSummary = $projectNames === []
            ? 'This team does not have any shared projects yet.'
            : 'Current shared projects: '.implode(', ', $projectNames).'.';

        return (new MailMessage)
            ->subject('You were invited to join a Testify team')
            ->greeting('You are invited to collaborate in Testify.')
            ->line(($team?->owner?->name ?? 'A Testify user').' invited you to join the team "'.($team?->name ?? 'Team').'".')
            ->line($projectSummary)
            ->line('This invitation expires on '.$this->invitation->expires_at?->toFormattedDateString().'.')
            ->action('Review Invitation', route('team-invitations.show', $this->invitation->token))
            ->line('If you already have a Testify account, sign in with this email to join the team immediately.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'team_id' => $this->invitation->team_id,
            'email' => $this->invitation->email,
            'token' => $this->invitation->token,
        ];
    }
}
