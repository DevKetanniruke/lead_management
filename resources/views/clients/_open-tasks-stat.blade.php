<x-stat label="Open follow-ups" :value="$openTasks->count()" :sub="$openTasks->filter(fn ($t) => $t->due_at?->isPast())->count().' overdue'" icon="clock" tone="bg-amber-50 text-amber-600" />
