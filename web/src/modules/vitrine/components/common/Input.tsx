'use client';

import React from 'react';
import { motion } from 'framer-motion';
import { AlertCircle } from 'lucide-react';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  icon?: React.ReactNode;
  iconPosition?: 'left' | 'right';
  helperText?: string;
  required?: boolean;
}

export function Input({
  label,
  error,
  icon,
  iconPosition = 'left',
  helperText,
  required = false,
  className = '',
  id,
  ...props
}: InputProps) {
  const inputId = id || `input-${Math.random().toString(36).substr(2, 9)}`;

  return (
    <div className="w-full">
      {label && (
        <label
          htmlFor={inputId}
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {label}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}

      <div className="relative">
        {icon && iconPosition === 'left' && (
          <div className="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 flex-shrink-0">
            {icon}
          </div>
        )}

        <motion.input
          whileFocus={{ scale: 1.01 }}
          transition={{ duration: 0.2 }}
          id={inputId}
          className={`
            w-full px-4 py-2.5 text-sm font-medium
            bg-white dark:bg-slate-900
            border border-slate-300 dark:border-slate-700
            rounded-xl
            transition-all duration-200
            focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent
            disabled:opacity-50 disabled:cursor-not-allowed
            ${error ? 'border-red-500 focus:ring-red-500' : ''}
            ${icon && iconPosition === 'left' ? 'pl-10' : ''}
            ${icon && iconPosition === 'right' ? 'pr-10' : ''}
            ${className}
          `.trim()}
          {...(props as any)}
        />

        {icon && iconPosition === 'right' && (
          <div className="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 dark:text-slate-500 flex-shrink-0">
            {icon}
          </div>
        )}
      </div>

      {error && (
        <motion.div
          initial={{ opacity: 0, y: -4 }}
          animate={{ opacity: 1, y: 0 }}
          className="flex items-center gap-1.5 mt-2 text-sm text-red-600 dark:text-red-400"
        >
          <AlertCircle className="w-4 h-4 flex-shrink-0" />
          {error}
        </motion.div>
      )}

      {helperText && !error && (
        <p className="mt-2 text-sm text-slate-500 dark:text-slate-400">{helperText}</p>
      )}
    </div>
  );
}
