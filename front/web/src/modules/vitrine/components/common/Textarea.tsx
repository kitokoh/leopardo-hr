'use client';

import React, { useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { AlertCircle } from 'lucide-react';

interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  error?: string;
  helperText?: string;
  required?: boolean;
  autoResize?: boolean;
  maxRows?: number;
}

export function Textarea({
  label,
  error,
  helperText,
  required = false,
  autoResize = true,
  maxRows = 6,
  className = '',
  id,
  onChange,
  ...props
}: TextareaProps) {
  const textareaId = id || `textarea-${Math.random().toString(36).substr(2, 9)}`;
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    if (!autoResize || !textareaRef.current) return;

    const textarea = textareaRef.current;
    const resizeTextarea = () => {
      textarea.style.height = 'auto';
      const newHeight = Math.min(textarea.scrollHeight, maxRows * 24);
      textarea.style.height = `${newHeight}px`;
    };

    textarea.addEventListener('input', resizeTextarea);
    resizeTextarea();

    return () => textarea.removeEventListener('input', resizeTextarea);
  }, [autoResize, maxRows]);

  const handleChange = (e: React.ChangeEvent<HTMLTextAreaElement>) => {
    if (onChange) onChange(e);
  };

  return (
    <div className="w-full">
      {label && (
        <label
          htmlFor={textareaId}
          className="block text-sm font-semibold text-slate-900 dark:text-white mb-2"
        >
          {label}
          {required && <span className="text-red-500 ml-1">*</span>}
        </label>
      )}

      <motion.textarea
        whileFocus={{ scale: 1.01 }}
        transition={{ duration: 0.2 }}
        ref={textareaRef}
        id={textareaId}
        className={`
          w-full px-4 py-2.5 text-sm font-medium resize-none
          bg-white dark:bg-slate-900
          border border-slate-300 dark:border-slate-700
          rounded-xl
          transition-all duration-200
          focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-transparent
          disabled:opacity-50 disabled:cursor-not-allowed
          ${error ? 'border-red-500 focus:ring-red-500' : ''}
          ${className}
        `.trim()}
        onChange={handleChange}
        {...(props as any)}
      />

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
