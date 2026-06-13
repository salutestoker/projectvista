import { ProjectVistaShell } from '@/Components/ProjectVista/ProjectVistaShell';
import { StatusPill } from '@/Components/ProjectVista/StatusPill';
import { ProjectPayload, TimelineTaskPayload } from '@/types/projectvista';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, useForm } from '@inertiajs/react';
import { GripVertical } from 'lucide-react';
import { useMemo, useState } from 'react';

interface TimelineProps {
    project: ProjectPayload;
}

export default function Timeline({ project }: TimelineProps) {
    const [tasks, setTasks] = useState(project.timeline);
    const reorderForm = useForm({
        tasks: project.timeline.map((task) => ({
            id: task.id,
            sort_order: task.sort_order,
        })),
    });

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    const canReorder = project.permissions.can_edit_project;
    const ids = useMemo(() => tasks.map((task) => task.id), [tasks]);

    const handleDragEnd = (event: DragEndEvent) => {
        const { active, over } = event;

        if (!over || active.id === over.id) {
            return;
        }

        const oldIndex = tasks.findIndex((task) => task.id === active.id);
        const newIndex = tasks.findIndex((task) => task.id === over.id);
        const nextTasks = arrayMove(tasks, oldIndex, newIndex).map(
            (task, index) => ({
                ...task,
                sort_order: index + 1,
            }),
        );
        const payload = nextTasks.map((task) => ({
            id: task.id,
            sort_order: task.sort_order,
        }));

        setTasks(nextTasks);
        reorderForm.transform(() => ({ tasks: payload }));
        reorderForm.patch(route('projects.timeline.reorder', project.slug), {
            preserveScroll: true,
        });
    };

    return (
        <ProjectVistaShell
            title="Timeline"
            eyebrow={project.name}
            role={project.role}
            project={project}
        >
            <Head title={`${project.name} Timeline`} />
            <div className="mb-5 rounded-lg border border-white/10 bg-white/[0.04] p-5">
                <h2 className="text-xl font-semibold">{project.phase}</h2>
                <p className="mt-2 text-white/60">
                    Timeline milestones translate field progress into clear
                    homeowner expectations.
                </p>
            </div>

            {canReorder ? (
                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCenter}
                    onDragEnd={handleDragEnd}
                >
                    <SortableContext
                        items={ids}
                        strategy={verticalListSortingStrategy}
                    >
                        <div className="space-y-3">
                            {tasks.map((task) => (
                                <SortableTask key={task.id} task={task} />
                            ))}
                        </div>
                    </SortableContext>
                </DndContext>
            ) : (
                <div className="space-y-3">
                    {tasks.map((task) => (
                        <TimelineCard key={task.id} task={task} />
                    ))}
                </div>
            )}
        </ProjectVistaShell>
    );
}

function SortableTask({ task }: { task: TimelineTaskPayload }) {
    const { attributes, listeners, setNodeRef, transform, transition } =
        useSortable({
            id: task.id,
        });

    return (
        <div
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition }}
            className="grid gap-3 rounded-lg border border-white/10 bg-white/[0.04] p-4 md:grid-cols-[36px_1fr_auto]"
        >
            <button
                type="button"
                className="grid h-9 w-9 place-items-center rounded-md border border-white/10 text-white/45"
                {...attributes}
                {...listeners}
            >
                <GripVertical className="h-4 w-4" />
            </button>
            <TimelineBody task={task} />
            <StatusPill status={task.status} />
        </div>
    );
}

function TimelineCard({ task }: { task: TimelineTaskPayload }) {
    return (
        <div className="grid gap-3 rounded-lg border border-white/10 bg-white/[0.04] p-4 md:grid-cols-[1fr_auto]">
            <TimelineBody task={task} />
            <StatusPill status={task.status} />
        </div>
    );
}

function TimelineBody({ task }: { task: TimelineTaskPayload }) {
    return (
        <div>
            <div className="text-xs tracking-[0.25em] text-[#d6b36a] uppercase">
                {task.phase}
            </div>
            <h3 className="mt-1 text-lg font-semibold">{task.title}</h3>
            <p className="mt-2 text-sm text-white/60">{task.description}</p>
            <div className="mt-3 flex flex-wrap gap-3 text-xs text-white/45">
                <span>Starts {task.starts_on ?? 'TBD'}</span>
                <span>Due {task.due_on ?? 'TBD'}</span>
                {task.requires_acknowledgement && (
                    <span>Requires acknowledgement</span>
                )}
            </div>
        </div>
    );
}
