"use client";

import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import {
  Command,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandItemIndicator,
  CommandList,
  CommandSeparator,
} from "@/components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { Separator } from "@/components/ui/separator";
import { cn } from "@/lib/utils";
import Add01Icon from "~icons/hugeicons/add-01";

type MailLogTableFacetedFilterProps = {
  title: string;
  options: {
    label: string;
    value: string;
    count?: number;
  }[];
  selectedValues: string[];
  onChange: (values: string[]) => void;
};

export function MailLogTableFacetedFilter({
  title,
  options,
  selectedValues,
  onChange,
}: MailLogTableFacetedFilterProps) {
  const selectedValueSet = new Set(selectedValues);

  return (
    <Popover>
      <PopoverTrigger
        render={<Button variant="outline" size="sm" className="h-8 rounded-md border-dashed" />}
      >
        <Add01Icon data-icon="inline-start" />
        {title}
        {selectedValueSet.size > 0 ? (
          <>
            <Separator orientation="vertical" className="mx-2 h-4 data-vertical:self-auto" />
            <Badge variant="secondary" className="rounded-sm px-1 font-normal lg:hidden">
              {selectedValueSet.size}
            </Badge>
            <div className="hidden gap-1 lg:flex">
              {selectedValueSet.size > 2 ? (
                <Badge variant="secondary" className="rounded-sm px-1 font-normal">
                  {selectedValueSet.size} selected
                </Badge>
              ) : (
                options
                  .filter((option) => selectedValueSet.has(option.value))
                  .map((option) => (
                    <Badge
                      key={option.value}
                      variant="secondary"
                      className="rounded-sm px-1 font-normal"
                    >
                      {option.label}
                    </Badge>
                  ))
              )}
            </div>
          </>
        ) : null}
      </PopoverTrigger>
      <PopoverContent align="start" className="w-[210px] rounded-md p-0">
        <Command>
          <CommandInput placeholder={title} />
          <CommandList>
            <CommandEmpty>No results found.</CommandEmpty>
            <CommandGroup>
              {options.map((option) => {
                const isSelected = selectedValueSet.has(option.value);

                return (
                  <CommandItem
                    key={option.value}
                    onSelect={() => {
                      const nextValues = new Set(selectedValues);

                      if (isSelected) {
                        nextValues.delete(option.value);
                      } else {
                        nextValues.add(option.value);
                      }

                      onChange(Array.from(nextValues));
                    }}
                  >
                    <div
                      className={cn(
                        "flex size-4 items-center justify-center rounded-[4px] border",
                        isSelected
                          ? "border-primary bg-primary text-primary-foreground"
                          : "border-input [&_svg]:invisible",
                      )}
                    >
                      <CommandItemIndicator />
                    </div>
                    <span>{option.label}</span>
                    {option.count ? (
                      <span className="ml-auto flex size-4 items-center justify-center font-mono text-xs text-muted-foreground">
                        {option.count}
                      </span>
                    ) : null}
                  </CommandItem>
                );
              })}
            </CommandGroup>
            {selectedValueSet.size > 0 ? (
              <>
                <CommandSeparator />
                <CommandGroup>
                  <CommandItem onSelect={() => onChange([])} className="justify-center text-center">
                    Clear filters
                  </CommandItem>
                </CommandGroup>
              </>
            ) : null}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

export default MailLogTableFacetedFilter;
