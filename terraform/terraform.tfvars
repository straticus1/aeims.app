# AEIMS Terraform Variables
# Production deployment configuration

environment = "prod"
domain_name = "aeims.app"

# Cloudflare Zone ID for aeims.app (placeholder - update with real value)
cloudflare_zone_id = "placeholder-zone-id"

# Sites configuration - matches discovered sites
sites = [
  {
    domain  = "nycflirts.com"
    theme   = "nyc"
    enabled = true
  },
  {
    domain  = "flirts.nyc"
    theme   = "nyc"
    enabled = true
  }
]