---
name: storefront-theme-system
description: Storefront must support seller-selectable themes/templates; seller panel itself is one fixed system-branded design
metadata: 
  node_type: memory
  type: project
  originSessionId: 95e26b90-4a53-4f6a-ad4d-f806f2cbc32c
---

In the MyShop SaaS (shop.kwasniak.org), the customer-facing STOREFRONT must support multiple looks: the seller picks a template/theme for how their shop front appears. The theming layer must be designed in from the start (not bolted on later).

Distinction:
- STOREFRONT (customer-facing) = themeable, seller chooses the look.
- SELLER PANEL = a single design branded with the whole system (NOT themeable).

Status: remembered for later. We start the build with the Seller (and Admin, needed to create sellers), whose panel uses the one system-branded design. The theme system for storefronts comes when we build the customer-facing shop.

Related: [[shared-hosting-constraints]].
