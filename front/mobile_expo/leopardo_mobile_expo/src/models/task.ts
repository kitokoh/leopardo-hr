export type ProjectTask = {
  id: number;
  title: string;
  description?: string | null;
  status: string;
  due_date?: string | null;
  priority?: string | null;
  project?: { id?: number; name?: string } | null;
  completed_minutes?: number | null;
};

export function isTaskDone(task: ProjectTask): boolean {
  return task.status === 'done' || task.status === 'completed';
}
