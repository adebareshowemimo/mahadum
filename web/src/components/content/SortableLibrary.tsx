import type { ReactNode } from 'react'
import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core'
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable'
import { CSS } from '@dnd-kit/utilities'
import { Icon, IconButton } from '@/components/ui'

export function SortableLibrary({ ids, disabled, onReorder, children }: { ids: number[]; disabled: boolean; onReorder: (ids: number[]) => void; children: ReactNode }) {
  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }))
  return <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={({ active, over }) => {
    if (disabled || !over || active.id === over.id) return
    const from = ids.indexOf(Number(active.id)), to = ids.indexOf(Number(over.id))
    if (from >= 0 && to >= 0) onReorder(arrayMove(ids, from, to))
  }}><SortableContext items={ids} strategy={verticalListSortingStrategy}>{children}</SortableContext></DndContext>
}

export function SortableLibraryItem({ id, label, disabled, children }: { id: number; label: string; disabled: boolean; children: ReactNode }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id, disabled })
  return <div ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }} className={`flex items-start gap-2 ${isDragging ? 'relative z-10 opacity-90 shadow-lg' : ''}`}>
    {!disabled && <IconButton aria-label={`Drag to reorder ${label}`} variant="ghost" className="mt-3 cursor-grab touch-none" {...attributes} {...listeners}><Icon name="grip" className="size-4" /></IconButton>}
    <div className="min-w-0 flex-1">{children}</div>
  </div>
}
