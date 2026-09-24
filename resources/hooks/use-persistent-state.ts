"use client";

import * as React from "react";

function readStoredValue<T>(key: string, initialValue: T): T {
  if (typeof window === "undefined") {
    return initialValue;
  }

  try {
    const storedValue = window.localStorage.getItem(key);

    return storedValue === null ? initialValue : (JSON.parse(storedValue) as T);
  } catch {
    return initialValue;
  }
}

export function usePersistentState<T>(
  key: string,
  initialValue: T,
): [T, React.Dispatch<React.SetStateAction<T>>] {
  const [value, setValue] = React.useState<T>(() => readStoredValue(key, initialValue));

  React.useEffect(() => {
    try {
      const storedValue = JSON.stringify(value);

      if (storedValue === undefined) {
        window.localStorage.removeItem(key);
      } else {
        window.localStorage.setItem(key, storedValue);
      }
    } catch {
      // Keep the state usable when browser storage is unavailable.
    }
  }, [key, value]);

  return [value, setValue];
}
