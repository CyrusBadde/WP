import { useState, useCallback } from 'react';
import {
  Sparkles,
  FolderPlus,
  Clock,
  Trash2,
  Code2,
  Globe,
  Server,
  FileText,
} from 'lucide-react';
import { cn, formatRelativeTime } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog';
import { useProjectStore } from '@/stores';
import type { ProjectTemplate } from '@/types';

interface TemplateCardProps {
  template: ProjectTemplate;
  name: string;
  description: string;
  icon: typeof Code2;
  selected: boolean;
  onClick: () => void;
}

function TemplateCard({ name, description, icon: Icon, selected, onClick }: TemplateCardProps) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'flex flex-col items-start gap-3 rounded-lg border-2 p-4 text-left transition-all hover:border-primary/50',
        selected ? 'border-primary bg-primary/5' : 'border-border'
      )}
    >
      <div
        className={cn(
          'flex h-10 w-10 items-center justify-center rounded-lg',
          selected ? 'bg-primary text-primary-foreground' : 'bg-muted'
        )}
      >
        <Icon className="h-5 w-5" />
      </div>
      <div>
        <h3 className="font-medium">{name}</h3>
        <p className="text-sm text-muted-foreground">{description}</p>
      </div>
    </button>
  );
}

interface RecentProjectCardProps {
  name: string;
  template: ProjectTemplate;
  modifiedAt: number;
  onClick: () => void;
  onDelete: () => void;
}

function RecentProjectCard({
  name,
  template,
  modifiedAt,
  onClick,
  onDelete,
}: RecentProjectCardProps) {
  const handleDelete = useCallback(
    (e: React.MouseEvent) => {
      e.stopPropagation();
      onDelete();
    },
    [onDelete]
  );

  return (
    <button
      onClick={onClick}
      className="group flex items-center gap-4 rounded-lg border border-border p-4 text-left transition-all hover:border-primary/50 hover:bg-accent"
    >
      <div className="flex h-12 w-12 items-center justify-center rounded-lg bg-muted">
        <FolderPlus className="h-6 w-6 text-muted-foreground" />
      </div>
      <div className="flex-1 min-w-0">
        <h3 className="font-medium truncate">{name}</h3>
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <span className="capitalize">{template}</span>
          <span>•</span>
          <Clock className="h-3 w-3" />
          <span>{formatRelativeTime(modifiedAt)}</span>
        </div>
      </div>
      <button
        onClick={handleDelete}
        className="p-2 rounded-lg opacity-0 group-hover:opacity-100 hover:bg-destructive hover:text-destructive-foreground transition-all"
        title="Delete project"
      >
        <Trash2 className="h-4 w-4" />
      </button>
    </button>
  );
}

const templates: Array<{
  template: ProjectTemplate;
  name: string;
  description: string;
  icon: typeof Code2;
}> = [
  {
    template: 'react',
    name: 'React App',
    description: 'A simple React application with TypeScript',
    icon: Code2,
  },
  {
    template: 'nextjs',
    name: 'Next.js App',
    description: 'Full-stack React framework with routing',
    icon: Globe,
  },
  {
    template: 'api',
    name: 'API Server',
    description: 'Express.js REST API with TypeScript',
    icon: Server,
  },
  {
    template: 'blank',
    name: 'Blank Project',
    description: 'Start from scratch with an empty project',
    icon: FileText,
  },
];

export function WelcomeScreen() {
  const { projects, createProject, openProject, deleteProject } = useProjectStore();
  const [isCreateDialogOpen, setIsCreateDialogOpen] = useState(false);
  const [projectName, setProjectName] = useState('');
  const [selectedTemplate, setSelectedTemplate] = useState<ProjectTemplate>('react');

  const handleCreateProject = useCallback(() => {
    if (projectName.trim()) {
      const project = createProject(projectName.trim(), selectedTemplate);
      setIsCreateDialogOpen(false);
      setProjectName('');
      openProject(project.id);
    }
  }, [projectName, selectedTemplate, createProject, openProject]);

  const handleOpenProject = useCallback(
    (projectId: string) => {
      openProject(projectId);
    },
    [openProject]
  );

  const handleDeleteProject = useCallback(
    (projectId: string) => {
      if (confirm('Are you sure you want to delete this project?')) {
        deleteProject(projectId);
      }
    },
    [deleteProject]
  );

  const recentProjects = projects
    .slice()
    .sort((a, b) => b.modifiedAt - a.modifiedAt)
    .slice(0, 6);

  return (
    <div className="flex h-full flex-col items-center justify-center p-8">
      <div className="w-full max-w-4xl">
        {/* Header */}
        <div className="mb-12 text-center">
          <div className="mb-4 flex items-center justify-center gap-2">
            <Sparkles className="h-10 w-10 text-primary" />
            <h1 className="text-4xl font-bold">FlowCode</h1>
          </div>
          <p className="text-lg text-muted-foreground">
            AI-powered development platform for the modern developer
          </p>
        </div>

        {/* Quick Actions */}
        <div className="mb-12">
          <h2 className="mb-4 text-lg font-semibold">Get Started</h2>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {templates.map((t) => (
              <TemplateCard
                key={t.template}
                {...t}
                selected={false}
                onClick={() => {
                  setSelectedTemplate(t.template);
                  setProjectName(`My ${t.name}`);
                  setIsCreateDialogOpen(true);
                }}
              />
            ))}
          </div>
        </div>

        {/* Recent Projects */}
        {recentProjects.length > 0 && (
          <div>
            <h2 className="mb-4 text-lg font-semibold">Recent Projects</h2>
            <div className="grid gap-3 sm:grid-cols-2">
              {recentProjects.map((project) => (
                <RecentProjectCard
                  key={project.id}
                  name={project.name}
                  template={project.template}
                  modifiedAt={project.modifiedAt}
                  onClick={() => handleOpenProject(project.id)}
                  onDelete={() => handleDeleteProject(project.id)}
                />
              ))}
            </div>
          </div>
        )}

        {/* Create Project Dialog */}
        <Dialog open={isCreateDialogOpen} onOpenChange={setIsCreateDialogOpen}>
          <DialogContent>
            <DialogHeader>
              <DialogTitle>Create New Project</DialogTitle>
              <DialogDescription>
                Choose a template and give your project a name.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4 py-4">
              <div>
                <label className="text-sm font-medium mb-2 block">Project Name</label>
                <Input
                  value={projectName}
                  onChange={(e) => setProjectName(e.target.value)}
                  placeholder="My Awesome Project"
                  autoFocus
                />
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">Template</label>
                <div className="grid gap-3 grid-cols-2">
                  {templates.map((t) => (
                    <TemplateCard
                      key={t.template}
                      {...t}
                      selected={selectedTemplate === t.template}
                      onClick={() => setSelectedTemplate(t.template)}
                    />
                  ))}
                </div>
              </div>
            </div>

            <DialogFooter>
              <Button variant="outline" onClick={() => setIsCreateDialogOpen(false)}>
                Cancel
              </Button>
              <Button onClick={handleCreateProject} disabled={!projectName.trim()}>
                Create Project
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>

        {/* Demo Notice */}
        <div className="mt-12 rounded-lg border border-yellow-500/30 bg-yellow-500/10 p-4 text-center">
          <p className="text-sm text-yellow-600 dark:text-yellow-500">
            <strong>Demo Mode:</strong> This is a browser-based MVP. Projects are stored locally.
            For production features like Git sync and deployments, see Phase 3 roadmap.
          </p>
        </div>
      </div>
    </div>
  );
}
