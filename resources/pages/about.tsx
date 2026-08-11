import type { ComponentType } from "react";

import { JooosiMailLogo } from "@/components/jooosi-mail-logo";
import { Badge } from "@/components/reui/badge";
import {
  Frame,
  FrameDescription,
  FrameFooter,
  FrameHeader,
  FramePanel,
  FrameTitle,
} from "@/components/reui/frame";
import { IconTile } from "@/components/reui/icon-tile";
import { buttonVariants } from "@/components/ui/button";
import { Separator } from "@/components/ui/separator";
import { getAdminRuntime } from "@/lib/admin-api";
import { cn } from "@/lib/utils";
import Activity01Icon from "~icons/hugeicons/activity-01";
import Award01Icon from "~icons/hugeicons/award-01";
import CodeIcon from "~icons/hugeicons/code";
import Database01Icon from "~icons/hugeicons/database-01";
import Facebook01Icon from "~icons/hugeicons/facebook-01";
import File01Icon from "~icons/hugeicons/file-01";
import Github01Icon from "~icons/hugeicons/github-01";
import HeartCheckIcon from "~icons/hugeicons/heart-check";
import Package01Icon from "~icons/hugeicons/package-01";
import StarIcon from "~icons/hugeicons/star";
import UserGroupIcon from "~icons/hugeicons/user-group";
import ZapIcon from "~icons/hugeicons/zap";
import KofiIcon from "~icons/simple-icons/kofi";

type Capability = {
  title: string;
  description: string;
  icon: ComponentType<{ className?: string }>;
};

type Sponsor = {
  name: string;
  description: string;
  href: string;
  iconSrc: string;
};

const capabilities: Capability[] = [
  {
    title: "Multiple providers",
    description: "Run SMTP and API providers through one consistent delivery layer.",
    icon: Database01Icon,
  },
  {
    title: "Resilient delivery",
    description: "Queue busy periods and recover through fallback connections.",
    icon: ZapIcon,
  },
  {
    title: "Admin and CLI",
    description: "Operate connections, logs, and delivery policy from WordPress or WP-CLI.",
    icon: CodeIcon,
  },
  {
    title: "Live operations",
    description: "Track provider availability, queue pressure, and delivery outcomes.",
    icon: Activity01Icon,
  },
];

const sponsors: Sponsor[] = [
  {
    name: "Jooosi",
    description: "Open-source tools and products for WordPress",
    href: "https://jooo.si",
    iconSrc: new URL("../icons/jooosi.svg", import.meta.url).href,
  },
  {
    name: "LiveCanvas",
    description: "Visual Site Builder for WordPress",
    href: "https://livecanvas.com",
    iconSrc: new URL("../icons/livecanvas.svg", import.meta.url).href,
  },
];

const sponsorshipBenefits: Capability[] = [
  {
    title: "Release visibility",
    description: "Your brand icon can ship with supported plugin releases.",
    icon: Package01Icon,
  },
  {
    title: "Project documentation",
    description: "Sponsors are recognized across plugin documentation.",
    icon: File01Icon,
  },
  {
    title: "Admin recognition",
    description: "Featured support helps sustain the admin experience.",
    icon: Award01Icon,
  },
  {
    title: "Developer reach",
    description: "Connect with the wider WordPress developer community.",
    icon: UserGroupIcon,
  },
];

export default function AboutPage() {
  const { pluginVersion } = getAdminRuntime();

  return (
    <div className="@container/main flex flex-1 flex-col gap-6 px-4 py-4 lg:px-6 lg:py-6">
      <Frame stacked spacing="lg">
        <FrameHeader className="flex-row items-center justify-between gap-6">
          <div className="flex min-w-0 items-center gap-4">
            <IconTile variant="frame" className="size-14 shrink-0">
              <JooosiMailLogo className="size-8" />
            </IconTile>
            <div className="flex min-w-0 flex-col gap-1">
              <FrameTitle className="text-xl">Jooosi Mail</FrameTitle>
              <FrameDescription className="max-w-3xl">
                A durable WordPress email delivery layer powered by Symfony Mailer, queues, provider
                failover, webhooks, and operational observability.
              </FrameDescription>
            </div>
          </div>
          <Badge variant="primary-light" radius="full">
            Version {pluginVersion}
          </Badge>
        </FrameHeader>

        <FramePanel className="grid grid-cols-4 p-0!">
          {capabilities.map((capability, index) => {
            const Icon = capability.icon;

            return (
              <div key={capability.title} className="relative flex min-w-0 flex-col gap-4 p-5">
                <IconTile variant="frame" aria-hidden="true">
                  <Icon />
                </IconTile>
                <div className="flex flex-col gap-1">
                  <h3 className="text-sm font-semibold">{capability.title}</h3>
                  <p className="text-sm leading-relaxed text-muted-foreground">
                    {capability.description}
                  </p>
                </div>
                {index < capabilities.length - 1 ? (
                  <Separator orientation="vertical" className="absolute top-0 right-0" />
                ) : null}
              </div>
            );
          })}
        </FramePanel>

        <FrameFooter className="flex-row flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Badge variant="success-light">GPL-3.0-or-later</Badge>
            <span>Open source</span>
          </div>
          <div className="flex flex-wrap gap-2">
            <a
              className={buttonVariants({ variant: "outline", size: "sm" })}
              href="https://github.com/jooosi-project/jooosi-mail"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Github01Icon data-icon="inline-start" />
              GitHub repository
            </a>
            <a
              className={buttonVariants({ variant: "outline", size: "sm" })}
              href="https://wind.press/go/facebook"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Facebook01Icon data-icon="inline-start" />
              Community
            </a>
          </div>
        </FrameFooter>
      </Frame>

      <div className="grid grid-cols-[minmax(0,1.1fr)_minmax(22rem,0.9fr)] gap-4">
        <Frame stacked spacing="sm">
          <FrameHeader>
            <FrameTitle>Open-source sponsorship</FrameTitle>
            <FrameDescription>
              Every contribution supports maintenance across the complete WordPress plugin
              portfolio.
            </FrameDescription>
          </FrameHeader>
          <FramePanel className="p-0!">
            <div className="grid grid-cols-2">
              {sponsorshipBenefits.map((benefit) => {
                const Icon = benefit.icon;

                return (
                  <div
                    key={benefit.title}
                    className="flex items-start gap-3 border-b p-4 odd:border-r"
                  >
                    <IconTile variant="frame" aria-hidden="true">
                      <Icon />
                    </IconTile>
                    <div className="flex min-w-0 flex-col gap-1">
                      <p className="text-sm font-medium">{benefit.title}</p>
                      <p className="text-xs leading-relaxed text-muted-foreground">
                        {benefit.description}
                      </p>
                    </div>
                  </div>
                );
              })}
            </div>
          </FramePanel>
          <FrameFooter className="flex-row gap-2">
            <a
              className={cn(buttonVariants({ size: "sm" }), "flex-1")}
              href="https://github.com/sponsors/suasgn"
              target="_blank"
              rel="noopener noreferrer"
            >
              <Github01Icon data-icon="inline-start" />
              GitHub Sponsors
            </a>
            <a
              className={cn(buttonVariants({ variant: "outline", size: "sm" }), "flex-1")}
              href="https://ko-fi.com/Q5Q75XSF7"
              target="_blank"
              rel="noopener noreferrer"
            >
              <KofiIcon data-icon="inline-start" />
              Ko-fi
            </a>
          </FrameFooter>
        </Frame>

        <Frame stacked spacing="sm">
          <FrameHeader className="flex-row items-center gap-3">
            <IconTile variant="frame" aria-hidden="true">
              <HeartCheckIcon />
            </IconTile>
            <div className="flex flex-col gap-0.5">
              <FrameTitle>Proudly sponsored by</FrameTitle>
              <FrameDescription>Partners helping keep Jooosi Mail sustainable.</FrameDescription>
            </div>
          </FrameHeader>
          <FramePanel className="p-0!">
            {sponsors.map((sponsor, index) => (
              <a
                key={sponsor.name}
                className="relative flex items-center gap-4 p-4 transition-colors hover:bg-muted/50"
                href={sponsor.href}
                target="_blank"
                rel="noopener noreferrer"
              >
                <IconTile variant="frame" className="size-12">
                  <img src={sponsor.iconSrc} alt="" className="size-8 object-contain" />
                </IconTile>
                <span className="flex min-w-0 flex-1 flex-col gap-0.5">
                  <span className="text-sm font-medium">{sponsor.name}</span>
                  <span className="text-xs text-muted-foreground">{sponsor.description}</span>
                </span>
                <StarIcon aria-hidden="true" />
                {index < sponsors.length - 1 ? (
                  <Separator className="absolute bottom-0 left-0" />
                ) : null}
              </a>
            ))}
          </FramePanel>
        </Frame>
      </div>
    </div>
  );
}
