'use client';

import React from 'react';
import * as LucideIcons from 'lucide-react';

const safeLog = (..._args: unknown[]) => {};

export type IconName = keyof typeof LucideIcons;

interface IconProps {
  name: IconName;
  size?: number | 'sm' | 'md' | 'lg' | 'xl';
  className?: string;
  strokeWidth?: number;
}

const sizeMap: Record<string, number> = {
  sm: 16,
  md: 24,
  lg: 32,
  xl: 48,
};

export function Icon({ name, size = 'md', className = '', strokeWidth = 2 }: IconProps) {
  const IconComponent = LucideIcons[name] as React.ComponentType<any>;

  if (!IconComponent) {
    safeLog(`Icon "${name}" not found in lucide-react`);
    return null;
  }

  const sizeValue = typeof size === 'string' ? sizeMap[size] : size;

  return (
    <IconComponent
      size={sizeValue}
      strokeWidth={strokeWidth}
      className={className}
    />
  );
}

// Preset icons for common use cases
export const Icons = {
  // Navigation
  Menu: () => <LucideIcons.Menu className="w-5 h-5" />,
  X: () => <LucideIcons.X className="w-5 h-5" />,
  ChevronDown: () => <LucideIcons.ChevronDown className="w-4 h-4" />,
  ChevronUp: () => <LucideIcons.ChevronUp className="w-4 h-4" />,
  ChevronLeft: () => <LucideIcons.ChevronLeft className="w-4 h-4" />,
  ChevronRight: () => <LucideIcons.ChevronRight className="w-4 h-4" />,
  ArrowRight: () => <LucideIcons.ArrowRight className="w-4 h-4" />,
  ArrowLeft: () => <LucideIcons.ArrowLeft className="w-4 h-4" />,

  // Theme
  Sun: () => <LucideIcons.Sun className="w-4 h-4" />,
  Moon: () => <LucideIcons.Moon className="w-4 h-4" />,

  // Status
  Check: () => <LucideIcons.Check className="w-4 h-4" />,
  X2: () => <LucideIcons.X className="w-4 h-4" />,
  AlertCircle: () => <LucideIcons.AlertCircle className="w-4 h-4" />,
  Info: () => <LucideIcons.Info className="w-4 h-4" />,
  CheckCircle: () => <LucideIcons.CheckCircle className="w-4 h-4" />,

  // Forms
  Mail: () => <LucideIcons.Mail className="w-4 h-4" />,
  Lock: () => <LucideIcons.Lock className="w-4 h-4" />,
  Eye: () => <LucideIcons.Eye className="w-4 h-4" />,
  EyeOff: () => <LucideIcons.EyeOff className="w-4 h-4" />,
  Search: () => <LucideIcons.Search className="w-4 h-4" />,

  // Features
  Users: () => <LucideIcons.Users className="w-5 h-5" />,
  FileText: () => <LucideIcons.FileText className="w-5 h-5" />,
  BarChart3: () => <LucideIcons.BarChart3 className="w-5 h-5" />,
  Zap: () => <LucideIcons.Zap className="w-5 h-5" />,
  Shield: () => <LucideIcons.Shield className="w-5 h-5" />,
  Clock: () => <LucideIcons.Clock className="w-5 h-5" />,
  Globe: () => <LucideIcons.Globe className="w-5 h-5" />,
  Smartphone: () => <LucideIcons.Smartphone className="w-5 h-5" />,

  // Social - using available icons
  Share2: () => <LucideIcons.Share2 className="w-5 h-5" />,
  Link: () => <LucideIcons.Link className="w-5 h-5" />,
  Mail2: () => <LucideIcons.Mail className="w-5 h-5" />,
  MessageSquare: () => <LucideIcons.MessageSquare className="w-5 h-5" />,

  // Loading
  Loader: () => <LucideIcons.Loader2 className="w-4 h-4 animate-spin" />,

};