import { Text, Box } from "ink";
import Spinner from "ink-spinner";
import SelectInput from "ink-select-input";
import TextInput from "ink-text-input";
import { useInput } from "ink";

export function Loading({ message }: { message: string }) {
  return (
    <Text>
      <Text color="blue">
        <Spinner type="dots" />
      </Text>
      {" "}
      {message}
    </Text>
  );
}

export function Success({ message }: { message: string }) {
  return (
    <Text>
      <Text color="green">{"✓"}</Text> {message}
    </Text>
  );
}

export function ErrorMessage({ message }: { message: string }) {
  return (
    <Text>
      <Text color="red">{"✗"}</Text> {message}
    </Text>
  );
}

export function Confirm({
  message,
  onConfirm,
}: {
  message: string;
  onConfirm: (yes: boolean) => void;
}) {
  useInput((input) => {
    if (input.toLowerCase() === "y") onConfirm(true);
    if (input.toLowerCase() === "n") onConfirm(false);
  });

  return (
    <Text>
      <Text color="yellow">{"?"}</Text> {message}{" "}
      <Text dimColor>(y/n)</Text>
    </Text>
  );
}

export function Prompt({
  message,
  value,
  onChange,
  onSubmit,
  placeholder,
}: {
  message: string;
  value: string;
  onChange: (value: string) => void;
  onSubmit: (value: string) => void;
  placeholder?: string;
}) {
  return (
    <Box>
      <Text>
        <Text color="yellow">{"?"}</Text> {message}{" "}
      </Text>
      <TextInput
        value={value}
        onChange={onChange}
        onSubmit={onSubmit}
        placeholder={placeholder}
      />
    </Box>
  );
}

export function Select<T extends string>({
  message,
  items,
  onSelect,
}: {
  message: string;
  items: Array<{ label: string; value: T }>;
  onSelect: (value: T) => void;
}) {
  return (
    <Box flexDirection="column">
      <Text>
        <Text color="yellow">{"?"}</Text> {message}
      </Text>
      <SelectInput
        items={items}
        onSelect={(item) => onSelect(item.value)}
      />
    </Box>
  );
}
