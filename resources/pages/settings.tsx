import * as React from "react";
import Alert02Icon from "~icons/hugeicons/alert-02";

import { Alert, AlertDescription, AlertTitle } from "@/components/reui/alert";
import { Badge } from "@/components/reui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardAction,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/reui/frame-card";
import {
  Field,
  FieldContent,
  FieldDescription,
  FieldGroup,
  FieldLabel,
} from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Separator } from "@/components/ui/separator";
import { Skeleton } from "@/components/ui/skeleton";
import { Switch } from "@/components/ui/switch";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { useAdminQuery } from "@/hooks/use-admin-query";
import { formatAdminNumber } from "@/lib/admin-format";
import { getSettings, updateSettings, type AdminSettings } from "@/lib/admin-api";

type SettingsDraft = {
  interceptEnabled: boolean;
  sender: {
    email: string;
    name: string;
    forceEmail: boolean;
    forceName: boolean;
    returnPathMode: string;
    returnPathEmail: string;
  };
  logging: {
    emailEnabled: boolean;
    retentionMode: "forever" | "custom";
    retentionDays: string;
  };
  deliveryMode: string;
  routingStrategy: string;
  rateLimits: {
    minute: string;
    hour: string;
    day: string;
  };
  circuitBreaker: {
    threshold: string;
    windowSeconds: string;
    cooldownSeconds: string;
  };
  retry: {
    maxRetries: string;
    delaySeconds: string;
    multiplier: string;
    maxDelaySeconds: string;
  };
};

type SettingsNumberFieldProps = {
  id: string;
  label: string;
  value: string;
  min: number;
  description?: string;
  onChange: (value: string) => void;
};

function createDraft(settings: AdminSettings): SettingsDraft {
  return {
    interceptEnabled: settings.mail.intercept.enabled,
    sender: {
      email: settings.mail.sender.email,
      name: settings.mail.sender.name,
      forceEmail: settings.mail.sender.forceEmail,
      forceName: settings.mail.sender.forceName,
      returnPathMode: settings.mail.sender.returnPathMode,
      returnPathEmail: settings.mail.sender.returnPathEmail,
    },
    logging: {
      emailEnabled: settings.logging.email.enabled,
      retentionMode: settings.logging.email.retentionDays === null ? "forever" : "custom",
      retentionDays: String(settings.logging.email.retentionDays ?? 30),
    },
    deliveryMode: settings.delivery.mode,
    routingStrategy: settings.delivery.strategy,
    rateLimits: {
      minute: String(settings.routing.rateLimits.minute),
      hour: String(settings.routing.rateLimits.hour),
      day: String(settings.routing.rateLimits.day),
    },
    circuitBreaker: {
      threshold: String(settings.routing.circuitBreaker.threshold),
      windowSeconds: String(settings.routing.circuitBreaker.windowSeconds),
      cooldownSeconds: String(settings.routing.circuitBreaker.cooldownSeconds),
    },
    retry: {
      maxRetries: String(settings.queue.retry.maxRetries),
      delaySeconds: String(settings.queue.retry.delaySeconds),
      multiplier: String(settings.queue.retry.multiplier),
      maxDelaySeconds: String(settings.queue.retry.maxDelaySeconds),
    },
  };
}

function toPositiveInteger(value: string, fallback: number, minimum = 0): number {
  const parsedValue = Number.parseInt(value, 10);

  if (Number.isNaN(parsedValue)) {
    return fallback;
  }

  return Math.max(minimum, parsedValue);
}

function formatIntegerValue(value: string): string {
  const parsedValue = Number.parseInt(value, 10);

  if (Number.isNaN(parsedValue)) {
    return "-";
  }

  return formatAdminNumber(parsedValue);
}

function formatDurationValue(value: string): string {
  const parsedValue = Number.parseInt(value, 10);

  if (Number.isNaN(parsedValue)) {
    return "-";
  }

  if (parsedValue >= 60 && parsedValue % 60 === 0) {
    return `${formatAdminNumber(parsedValue / 60)} min`;
  }

  return `${formatAdminNumber(parsedValue)}s`;
}

function createRetryTrail(draft: SettingsDraft): string {
  const firstDelay = toPositiveInteger(draft.retry.delaySeconds, 60, 1);
  const multiplier = toPositiveInteger(draft.retry.multiplier, 2, 1);
  const maxDelay = toPositiveInteger(draft.retry.maxDelaySeconds, 900, 1);
  const secondDelay = Math.min(firstDelay * multiplier, maxDelay);
  const thirdDelay = Math.min(secondDelay * multiplier, maxDelay);

  return [firstDelay, secondDelay, thirdDelay]
    .map((delay) => formatDurationValue(String(delay)))
    .join(" -> ");
}

function SettingsNumberField({
  id,
  label,
  value,
  min,
  description,
  onChange,
}: SettingsNumberFieldProps) {
  return (
    <Field className="gap-2">
      <FieldLabel htmlFor={id}>{label}</FieldLabel>
      <Input
        id={id}
        type="number"
        inputMode="numeric"
        min={min}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
      {description ? <FieldDescription>{description}</FieldDescription> : null}
    </Field>
  );
}

function SettingsLoadingState() {
  return (
    <div className="@container/main flex flex-1 flex-col gap-4 px-4 py-4 lg:px-6 lg:py-6">
      <Card>
        <CardHeader className="gap-3">
          <Skeleton className="h-5 w-32" />
          <Skeleton className="h-8 w-full max-w-md" />
          <Skeleton className="h-4 w-full max-w-xl" />
        </CardHeader>
        <CardContent>
          <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
            <Skeleton className="h-24" />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default function SettingsPage() {
  const loadSettings = React.useCallback(() => getSettings(), []);
  const { data, loading, error, refresh } = useAdminQuery(loadSettings);
  const [draft, setDraft] = React.useState<SettingsDraft | null>(null);
  const [saveError, setSaveError] = React.useState<string | null>(null);
  const [saving, setSaving] = React.useState(false);

  React.useEffect(() => {
    if (!data) {
      return;
    }

    setDraft(createDraft(data.settings));
  }, [data]);

  const setRateLimit = (key: keyof SettingsDraft["rateLimits"], value: string) => {
    setDraft((currentDraft) =>
      currentDraft
        ? {
            ...currentDraft,
            rateLimits: {
              ...currentDraft.rateLimits,
              [key]: value,
            },
          }
        : currentDraft,
    );
  };

  const setCircuitBreaker = (key: keyof SettingsDraft["circuitBreaker"], value: string) => {
    setDraft((currentDraft) =>
      currentDraft
        ? {
            ...currentDraft,
            circuitBreaker: {
              ...currentDraft.circuitBreaker,
              [key]: value,
            },
          }
        : currentDraft,
    );
  };

  const setRetry = (key: keyof SettingsDraft["retry"], value: string) => {
    setDraft((currentDraft) =>
      currentDraft
        ? {
            ...currentDraft,
            retry: {
              ...currentDraft.retry,
              [key]: value,
            },
          }
        : currentDraft,
    );
  };

  const handleSave = async () => {
    if (!draft || !data) {
      return;
    }

    setSaving(true);
    setSaveError(null);

    try {
      const response = await updateSettings({
        mail: {
          intercept: {
            enabled: draft.interceptEnabled,
          },
          sender: {
            email: draft.sender.email.trim(),
            name: draft.sender.name.trim(),
            forceEmail: draft.sender.forceEmail,
            forceName: draft.sender.forceName,
            returnPathMode: draft.sender.returnPathMode,
            returnPathEmail: draft.sender.returnPathEmail.trim(),
          },
        },
        logging: {
          email: {
            enabled: draft.logging.emailEnabled,
            retentionDays:
              draft.logging.retentionMode === "forever"
                ? null
                : toPositiveInteger(
                    draft.logging.retentionDays,
                    data.settings.logging.email.retentionDays ?? 30,
                    1,
                  ),
          },
        },
        delivery: {
          mode: draft.deliveryMode,
          strategy: draft.routingStrategy,
        },
        routing: {
          rateLimits: {
            minute: toPositiveInteger(
              draft.rateLimits.minute,
              data.settings.routing.rateLimits.minute,
            ),
            hour: toPositiveInteger(draft.rateLimits.hour, data.settings.routing.rateLimits.hour),
            day: toPositiveInteger(draft.rateLimits.day, data.settings.routing.rateLimits.day),
          },
          circuitBreaker: {
            threshold: toPositiveInteger(
              draft.circuitBreaker.threshold,
              data.settings.routing.circuitBreaker.threshold,
            ),
            windowSeconds: toPositiveInteger(
              draft.circuitBreaker.windowSeconds,
              data.settings.routing.circuitBreaker.windowSeconds,
              1,
            ),
            cooldownSeconds: toPositiveInteger(
              draft.circuitBreaker.cooldownSeconds,
              data.settings.routing.circuitBreaker.cooldownSeconds,
            ),
          },
        },
        queue: {
          retry: {
            maxRetries: toPositiveInteger(
              draft.retry.maxRetries,
              data.settings.queue.retry.maxRetries,
            ),
            delaySeconds: toPositiveInteger(
              draft.retry.delaySeconds,
              data.settings.queue.retry.delaySeconds,
              1,
            ),
            multiplier: toPositiveInteger(
              draft.retry.multiplier,
              data.settings.queue.retry.multiplier,
              1,
            ),
            maxDelaySeconds: toPositiveInteger(
              draft.retry.maxDelaySeconds,
              data.settings.queue.retry.maxDelaySeconds,
              1,
            ),
          },
        },
      });

      setDraft(createDraft(response.settings));
      await refresh();
    } catch (caughtError) {
      setSaveError(
        caughtError instanceof Error ? caughtError.message : "The settings could not be saved.",
      );
    } finally {
      setSaving(false);
    }
  };

  if (loading && !draft) {
    return <SettingsLoadingState />;
  }

  if (!draft || !data) {
    return (
      <div className="flex flex-1 flex-col px-4 py-4 lg:px-6 lg:py-6">
        <Card>
          <CardHeader>
            <CardTitle>Settings unavailable</CardTitle>
            <CardDescription>{error ?? "The settings screen could not be loaded."}</CardDescription>
          </CardHeader>
          <CardContent>
            <Button type="button" onClick={() => void refresh()}>
              Try again
            </Button>
          </CardContent>
        </Card>
      </div>
    );
  }

  return (
    <div className="@container/main flex flex-1 flex-col gap-4 py-4 md:gap-6 md:py-6">
      <div className="flex flex-col gap-3 px-4 lg:flex-row lg:items-center lg:justify-between lg:px-6">
        <div className="flex flex-col gap-1">
          <h2 className="text-xl font-semibold tracking-tight">Plugin settings</h2>
          <p className="text-sm text-muted-foreground">
            Configure delivery policy, routing safeguards, and retry behavior for the whole plugin.
          </p>
        </div>
        <Button type="button" onClick={() => void handleSave()} disabled={saving}>
          {saving ? "Saving..." : "Save settings"}
        </Button>
      </div>

      {error || saveError ? (
        <div className="px-4 lg:px-6">
          <Card>
            <CardContent className="py-4 text-sm text-destructive">
              {saveError ?? error}
            </CardContent>
          </Card>
        </div>
      ) : null}

      <Tabs defaultValue="delivery" className="gap-4 px-4 lg:px-6">
        <TabsList variant="line" className="w-full justify-start overflow-x-auto">
          <TabsTrigger value="delivery">Delivery</TabsTrigger>
          <TabsTrigger value="logs">Logs</TabsTrigger>
          <TabsTrigger value="sender">Sender</TabsTrigger>
          <TabsTrigger value="limits">Limits</TabsTrigger>
          <TabsTrigger value="recovery">Recovery</TabsTrigger>
        </TabsList>

        <TabsContent value="delivery" className="flex flex-col gap-4">
          <Card>
            <CardHeader>
              <CardTitle>Mail flow</CardTitle>
              <CardDescription>
                Control interception and routing defaults for every outgoing WordPress message.
              </CardDescription>
              <CardAction>
                <Badge variant={draft.interceptEnabled ? "default" : "outline"}>
                  {draft.interceptEnabled ? "Live" : "Bypassed"}
                </Badge>
              </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-6">
              <Field orientation="horizontal" className="gap-4 rounded-xl border bg-muted/30 p-4">
                <FieldContent className="gap-1">
                  <FieldLabel htmlFor="mail-intercept-enabled">
                    Enable wp_mail interception
                  </FieldLabel>
                  <FieldDescription>
                    When enabled, Jooosi Mail takes over delivery instead of letting WordPress send
                    mail directly.
                  </FieldDescription>
                </FieldContent>
                <Switch
                  id="mail-intercept-enabled"
                  checked={draft.interceptEnabled}
                  onCheckedChange={(checked) => {
                    setDraft((currentDraft) =>
                      currentDraft
                        ? {
                            ...currentDraft,
                            interceptEnabled: checked,
                          }
                        : currentDraft,
                    );
                  }}
                />
              </Field>

              <Separator />

              <FieldGroup className="grid gap-4 md:grid-cols-2 **:data-[slot=field]:gap-2">
                <Field>
                  <FieldLabel htmlFor="delivery-mode">Delivery mode</FieldLabel>
                  <Select
                    value={draft.deliveryMode}
                    onValueChange={(value) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              deliveryMode: value ?? currentDraft.deliveryMode,
                            }
                          : currentDraft,
                      );
                    }}
                  >
                    <SelectTrigger id="delivery-mode" className="w-full">
                      <SelectValue placeholder="Select delivery mode" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectGroup>
                        {data.options.deliveryModes.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {option.label}
                          </SelectItem>
                        ))}
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                  <FieldDescription>
                    Async mode places messages in the queue; sync mode sends during the current
                    request.
                  </FieldDescription>
                </Field>

                <Field>
                  <FieldLabel htmlFor="routing-strategy">Routing strategy</FieldLabel>
                  <Select
                    value={draft.routingStrategy}
                    onValueChange={(value) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              routingStrategy: value ?? currentDraft.routingStrategy,
                            }
                          : currentDraft,
                      );
                    }}
                  >
                    <SelectTrigger id="routing-strategy" className="w-full">
                      <SelectValue placeholder="Select routing strategy" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectGroup>
                        {data.options.routingStrategies.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {option.label}
                          </SelectItem>
                        ))}
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                  <FieldDescription>
                    The strategy decides which eligible connection is tried first for each message.
                  </FieldDescription>
                </Field>
              </FieldGroup>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="logs" className="flex flex-col gap-4">
          <Card>
            <CardHeader>
              <CardTitle>Email logging</CardTitle>
              <CardDescription>
                Control whether terminal email logs are kept and how long completed records remain
                available.
              </CardDescription>
              <CardAction>
                <Badge variant={draft.logging.emailEnabled ? "default" : "outline"}>
                  {draft.logging.emailEnabled ? "Enabled" : "Deleted after delivery"}
                </Badge>
              </CardAction>
            </CardHeader>
            <CardContent className="flex flex-col gap-4">
              <div className="grid gap-4 md:grid-cols-2">
                <Field
                  orientation="horizontal"
                  className="flex-wrap gap-4 rounded-xl border bg-muted/30 p-4"
                >
                  <FieldContent className="gap-1">
                    <FieldLabel htmlFor="email-logging-enabled">Keep email logs</FieldLabel>
                    <FieldDescription>
                      When disabled, Jooosi Mail keeps the internal delivery record only until the
                      email is sent or permanently failed.
                    </FieldDescription>
                  </FieldContent>
                  <Switch
                    id="email-logging-enabled"
                    checked={draft.logging.emailEnabled}
                    onCheckedChange={(checked) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              logging: {
                                ...currentDraft.logging,
                                emailEnabled: checked,
                              },
                            }
                          : currentDraft,
                      );
                    }}
                  />
                  <Alert className="basis-full border-destructive/20 bg-destructive/10 p-3 text-xs text-destructive">
                    <Alert02Icon />
                    <AlertTitle className="text-xs text-destructive">
                      Warning: Email log deletion
                    </AlertTitle>
                    <AlertDescription className="text-xs text-muted-foreground text-wrap">
                      Disabling email logging will delete existing sent or permanently failed email
                      logs when you save.
                    </AlertDescription>
                  </Alert>
                </Field>

                <FieldGroup className="rounded-xl border bg-muted/30 p-4 **:data-[slot=field]:gap-2">
                  <Field>
                    <FieldLabel htmlFor="email-log-retention">Retention</FieldLabel>
                    <Select
                      value={draft.logging.retentionMode}
                      onValueChange={(value) => {
                        setDraft((currentDraft) =>
                          currentDraft
                            ? {
                                ...currentDraft,
                                logging: {
                                  ...currentDraft.logging,
                                  retentionMode: value === "custom" ? "custom" : "forever",
                                },
                              }
                            : currentDraft,
                        );
                      }}
                    >
                      <SelectTrigger id="email-log-retention" className="w-full">
                        <SelectValue placeholder="Select retention" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectGroup>
                          <SelectItem value="forever">Keep forever</SelectItem>
                          <SelectItem value="custom">Delete after a number of days</SelectItem>
                        </SelectGroup>
                      </SelectContent>
                    </Select>
                    <FieldDescription>
                      Forever is the default. Retention cleanup only deletes sent or permanently
                      failed logs.
                    </FieldDescription>
                  </Field>

                  {draft.logging.retentionMode === "custom" ? (
                    <SettingsNumberField
                      id="email-log-retention-days"
                      label="Retention days"
                      min={1}
                      value={draft.logging.retentionDays}
                      description="Terminal logs older than this many days are deleted automatically."
                      onChange={(value) => {
                        setDraft((currentDraft) =>
                          currentDraft
                            ? {
                                ...currentDraft,
                                logging: {
                                  ...currentDraft.logging,
                                  retentionDays: value,
                                },
                              }
                            : currentDraft,
                        );
                      }}
                    />
                  ) : null}
                </FieldGroup>
              </div>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="sender" className="flex flex-col gap-4">
          <Card>
            <CardHeader>
              <CardTitle>Sender identity</CardTitle>
              <CardDescription>
                Set the default From address and optional Return-Path policy used when a connection
                does not override it.
              </CardDescription>
            </CardHeader>
            <CardContent className="flex flex-col gap-5">
              <FieldGroup className="grid gap-4 md:grid-cols-2 **:data-[slot=field]:gap-2">
                <Field>
                  <FieldLabel htmlFor="sender-email">From Email</FieldLabel>
                  <Input
                    id="sender-email"
                    type="email"
                    value={draft.sender.email}
                    onChange={(event) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              sender: {
                                ...currentDraft.sender,
                                email: event.target.value,
                              },
                            }
                          : currentDraft,
                      );
                    }}
                    placeholder="Use WordPress admin email"
                  />
                  <FieldDescription>
                    Used when an email does not specify its own From header, unless a connection
                    overrides it.
                  </FieldDescription>
                </Field>

                <Field>
                  <FieldLabel htmlFor="sender-name">From Name</FieldLabel>
                  <Input
                    id="sender-name"
                    value={draft.sender.name}
                    onChange={(event) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              sender: {
                                ...currentDraft.sender,
                                name: event.target.value,
                              },
                            }
                          : currentDraft,
                      );
                    }}
                    placeholder="Use site name"
                  />
                  <FieldDescription>
                    The display name paired with the default From Email.
                  </FieldDescription>
                </Field>
              </FieldGroup>

              <FieldGroup className="grid gap-4 md:grid-cols-2">
                <Field orientation="horizontal" className="gap-4 rounded-xl border bg-muted/30 p-4">
                  <FieldContent className="gap-1">
                    <FieldLabel htmlFor="force-sender-email">Force From Email</FieldLabel>
                    <FieldDescription>
                      Override From email addresses supplied by plugins and themes.
                    </FieldDescription>
                  </FieldContent>
                  <Switch
                    id="force-sender-email"
                    checked={draft.sender.forceEmail}
                    onCheckedChange={(checked) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              sender: {
                                ...currentDraft.sender,
                                forceEmail: checked,
                              },
                            }
                          : currentDraft,
                      );
                    }}
                  />
                </Field>

                <Field orientation="horizontal" className="gap-4 rounded-xl border bg-muted/30 p-4">
                  <FieldContent className="gap-1">
                    <FieldLabel htmlFor="force-sender-name">Force From Name</FieldLabel>
                    <FieldDescription>
                      Override sender display names supplied by plugins and themes.
                    </FieldDescription>
                  </FieldContent>
                  <Switch
                    id="force-sender-name"
                    checked={draft.sender.forceName}
                    onCheckedChange={(checked) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              sender: {
                                ...currentDraft.sender,
                                forceName: checked,
                              },
                            }
                          : currentDraft,
                      );
                    }}
                  />
                </Field>
              </FieldGroup>

              <Separator />

              <FieldGroup className="grid gap-4 md:grid-cols-2 **:data-[slot=field]:gap-2">
                <Field>
                  <FieldLabel htmlFor="return-path-mode">Return-Path</FieldLabel>
                  <Select
                    value={draft.sender.returnPathMode}
                    onValueChange={(value) => {
                      setDraft((currentDraft) =>
                        currentDraft
                          ? {
                              ...currentDraft,
                              sender: {
                                ...currentDraft.sender,
                                returnPathMode: value ?? currentDraft.sender.returnPathMode,
                              },
                            }
                          : currentDraft,
                      );
                    }}
                  >
                    <SelectTrigger id="return-path-mode" className="w-full">
                      <SelectValue placeholder="Select Return-Path behavior" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectGroup>
                        {data.options.returnPathModes.map((option) => (
                          <SelectItem key={option.value} value={option.value}>
                            {option.label}
                          </SelectItem>
                        ))}
                      </SelectGroup>
                    </SelectContent>
                  </Select>
                  <FieldDescription>
                    Provider default is safest; matching From Email sets the envelope sender for
                    bounces.
                  </FieldDescription>
                </Field>

                {draft.sender.returnPathMode === "custom" ? (
                  <Field>
                    <FieldLabel htmlFor="return-path-email">Return-Path Email</FieldLabel>
                    <Input
                      id="return-path-email"
                      type="email"
                      value={draft.sender.returnPathEmail}
                      onChange={(event) => {
                        setDraft((currentDraft) =>
                          currentDraft
                            ? {
                                ...currentDraft,
                                sender: {
                                  ...currentDraft.sender,
                                  returnPathEmail: event.target.value,
                                },
                              }
                            : currentDraft,
                        );
                      }}
                      placeholder="bounces@example.com"
                    />
                    <FieldDescription>
                      Must be accepted by the selected provider or SMTP server.
                    </FieldDescription>
                  </Field>
                ) : null}
              </FieldGroup>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="limits" className="flex flex-col gap-4">
          <div className="grid gap-4 xl:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle>Global rate budgets</CardTitle>
                <CardDescription>
                  Apply safety caps before per-connection limits participate in routing decisions.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <FieldGroup className="grid gap-4 md:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                  <SettingsNumberField
                    id="rate-limit-minute"
                    label="Per minute"
                    min={0}
                    value={draft.rateLimits.minute}
                    description="Use 0 for no minute cap."
                    onChange={(value) => setRateLimit("minute", value)}
                  />
                  <SettingsNumberField
                    id="rate-limit-hour"
                    label="Per hour"
                    min={0}
                    value={draft.rateLimits.hour}
                    description="Use 0 for no hour cap."
                    onChange={(value) => setRateLimit("hour", value)}
                  />
                  <SettingsNumberField
                    id="rate-limit-day"
                    label="Per day"
                    min={0}
                    value={draft.rateLimits.day}
                    description="Use 0 for no daily cap."
                    onChange={(value) => setRateLimit("day", value)}
                  />
                </FieldGroup>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Circuit breaker</CardTitle>
                <CardDescription>
                  Temporarily sideline unhealthy providers after repeated delivery failures.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <FieldGroup className="grid gap-4 md:grid-cols-3 xl:grid-cols-1 2xl:grid-cols-3">
                  <SettingsNumberField
                    id="circuit-threshold"
                    label="Failure threshold"
                    min={0}
                    value={draft.circuitBreaker.threshold}
                    description="Failures allowed before a route is paused."
                    onChange={(value) => setCircuitBreaker("threshold", value)}
                  />
                  <SettingsNumberField
                    id="circuit-window"
                    label="Window seconds"
                    min={1}
                    value={draft.circuitBreaker.windowSeconds}
                    description="Rolling failure window to inspect."
                    onChange={(value) => setCircuitBreaker("windowSeconds", value)}
                  />
                  <SettingsNumberField
                    id="circuit-cooldown"
                    label="Cooldown seconds"
                    min={0}
                    value={draft.circuitBreaker.cooldownSeconds}
                    description="Wait time before the route can recover."
                    onChange={(value) => setCircuitBreaker("cooldownSeconds", value)}
                  />
                </FieldGroup>
              </CardContent>
            </Card>
          </div>
        </TabsContent>

        <TabsContent value="recovery" className="flex flex-col gap-4">
          <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_22rem]">
            <Card>
              <CardHeader>
                <CardTitle>Retry policy</CardTitle>
                <CardDescription>
                  Control how failed queue jobs wait before retrying and when they stop retrying.
                </CardDescription>
              </CardHeader>
              <CardContent>
                <FieldGroup className="grid gap-4 md:grid-cols-2">
                  <SettingsNumberField
                    id="retry-max"
                    label="Max retries"
                    min={0}
                    value={draft.retry.maxRetries}
                    description="Number of retry attempts after the first failure."
                    onChange={(value) => setRetry("maxRetries", value)}
                  />
                  <SettingsNumberField
                    id="retry-delay"
                    label="Base delay seconds"
                    min={1}
                    value={draft.retry.delaySeconds}
                    description="Initial wait before the first retry."
                    onChange={(value) => setRetry("delaySeconds", value)}
                  />
                  <SettingsNumberField
                    id="retry-multiplier"
                    label="Backoff multiplier"
                    min={1}
                    value={draft.retry.multiplier}
                    description="Multiplier applied after each failed retry."
                    onChange={(value) => setRetry("multiplier", value)}
                  />
                  <SettingsNumberField
                    id="retry-max-delay"
                    label="Max delay seconds"
                    min={1}
                    value={draft.retry.maxDelaySeconds}
                    description="Upper limit for any retry wait."
                    onChange={(value) => setRetry("maxDelaySeconds", value)}
                  />
                </FieldGroup>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Backoff preview</CardTitle>
                <CardDescription>Calculated from the values currently in the form.</CardDescription>
              </CardHeader>
              <CardContent className="flex flex-col gap-4 text-sm">
                <div className="flex flex-col gap-1">
                  <span className="text-muted-foreground">Retry trail</span>
                  <span className="font-medium">{createRetryTrail(draft)}</span>
                </div>
                <Separator />
                <div className="flex flex-col gap-1">
                  <span className="text-muted-foreground">Stop condition</span>
                  <span className="font-medium">
                    Stop after {formatIntegerValue(draft.retry.maxRetries)} retry attempts.
                  </span>
                </div>
                <div className="flex flex-col gap-1">
                  <span className="text-muted-foreground">Delay ceiling</span>
                  <span className="font-medium">
                    No retry waits longer than {formatDurationValue(draft.retry.maxDelaySeconds)}.
                  </span>
                </div>
              </CardContent>
            </Card>
          </div>
        </TabsContent>
      </Tabs>
    </div>
  );
}
