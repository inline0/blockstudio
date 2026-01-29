import { testType } from '../../utils/playwright-utils';

// Check for key default values rather than full JSON (environment-specific data varies)
testType(
  'repeater-outside',
  '"defaultValueLabel":"Three","repeater":[{"defaultNumberBothWrongDefault":false,"defaultValueLabel":"Three","defaultNumberArray":3'
);
