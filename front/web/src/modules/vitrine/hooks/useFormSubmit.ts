'use client';

import { useState, useCallback } from 'react';

interface UseFormSubmitOptions {
  onSuccess?: (data: any) => void;
  onError?: (error: Error) => void;
}

interface FormSubmitState {
  isLoading: boolean;
  error: Error | null;
  success: boolean;
}

export function useFormSubmit(options: UseFormSubmitOptions = {}) {
  const { onSuccess, onError } = options;
  const [state, setState] = useState<FormSubmitState>({
    isLoading: false,
    error: null,
    success: false,
  });

  const submit = useCallback(
    async (submitFn: () => Promise<any>) => {
      setState({ isLoading: true, error: null, success: false });

      try {
        const data = await submitFn();
        setState({ isLoading: false, error: null, success: true });
        onSuccess?.(data);
        return data;
      } catch (error) {
        const err = error instanceof Error ? error : new Error(String(error));
        setState({ isLoading: false, error: err, success: false });
        onError?.(err);
        throw err;
      }
    },
    [onSuccess, onError]
  );

  const reset = useCallback(() => {
    setState({ isLoading: false, error: null, success: false });
  }, []);

  return {
    ...state,
    submit,
    reset,
  };
}
