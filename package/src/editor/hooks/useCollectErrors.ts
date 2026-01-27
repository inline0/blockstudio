import { useDispatch, useSelect } from '@wordpress/data';
import { selectors } from '@/editor/store/selectors';

export const useCollectErrors = () => {
  const { setErrors } = useDispatch('blockstudio/editor');
  const errors = useSelect(
    (select) => (select('blockstudio/editor') as typeof selectors).getErrors(),
    [],
  );

  const addError = (error: string) => {
    setErrors([...errors, error]);
  };

  const removeError = (error: string) => {
    setErrors(errors.filter((e: string) => e !== error));
  };

  return { addError, removeError };
};
