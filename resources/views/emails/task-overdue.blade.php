<!DOCTYPE html>
<html>
<body>
    <p>Hi {{ $task->user->name }},</p>
    <p>Your task <strong>{{ $task->title }}</strong> was due on {{ $task->due_date->toFormattedDateString() }} and is still marked as "{{ $task->status->value }}".</p>
    @if ($task->description)
        <p>{{ $task->description }}</p>
    @endif
</body>
</html>
