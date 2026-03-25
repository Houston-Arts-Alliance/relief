# HAA SOAR Disaster Relief Application

A web-based intake form for the **Houston Arts Alliance SOAR (Supporting Our Arts Recovery) Disaster Relief Fund**, which provides rapid financial assistance to individual artists in Houston affected by emergencies and major disruptions, created and managed by HAA's Senior Data Analytics Manager.

**Live application:** [disaster-relief-eight.vercel.app](https://disaster-relief-eight.vercel.app/)

## About the Program

The SOAR Disaster Relief Fund helps Houston-based artists recover from natural disasters, public-health emergencies, extended utility outages, and other incidents that substantially disrupt artistic work or income. Eligible applicants are individual artists aged 18+ who reside within the City of Houston.

This application collects applicant information, confirms eligibility, and performs a needs assessment that generates a priority score and tier assignment based on factors including household size, social vulnerability index (SVI), housing status, property loss, and current needs.

## How It Works

The form is organized into four sections:

1. **About** — Program overview and eligibility summary
2. **Information** — Contact details, address, online presence, and household composition
3. **Eligibility** — Age, residency, artist status, artistic discipline, and CDC/ATSDR SVI lookup
4. **Assessment** — Vulnerability factors, housing status, assistance history, current needs, property loss, impact statement, and consent/signature

A priority score is calculated in real time on the Assessment page and assigns applicants to one of four tiers (Critical, High, Moderate, or Low priority).

## Tech Stack

This is a single-file static application with no build step and no external dependencies beyond a CDC SVI map embed.

- **HTML / CSS / JavaScript** — All contained in `index.html`
- **Deployment** — [Vercel](https://vercel.com)

## Local Development

Clone the repository and open `index.html` in a browser:

```bash
git clone https://github.com/Houston-Arts-Alliance/disaster-relief.git
cd disaster-relief
open index.html
```

Or serve it locally:

```bash
npx serve .
```

> **Note:** The embedded CDC/ATSDR SVI map (`<iframe>`) requires an internet connection to load.

## Deployment

The application is deployed to Vercel and rebuilds automatically on push to the `main` branch. No build configuration is required — Vercel serves `index.html` as a static site.

## Repository Structure

```
├── index.html      # Complete application (HTML, CSS, and JS)
├── README.md
├── LICENSE.md
└── .gitignore
```

## Contributing

All HAA projects are created, managed, and maintained by the Houston Arts Alliance Data and Analytics team-of-one, HAA's Senior Data Analytics Manager. If you'd like to contribute or report an issue, please open an issue or pull request on this repository.

## License

This project is licensed under the [MIT License](LICENSE.md).

## About Houston Arts Alliance

[Houston Arts Alliance](https://houstonartsalliance.com) is the City of Houston's designated local arts agency and the partner of the Mayor's Office of Cultural Affairs for arts grantmaking and civic art investments. HAA's privately funded Disaster Services division provides rapid crisis response to Houston's arts sector and helps build long-term resilience.
