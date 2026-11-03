# ==========================================================================
#  Resources: EKS / backend.tf (Storing tfstate)
# --------------------------------------------------------------------------
#  Description
# --------------------------------------------------------------------------
#    - S3 Bucket Path
#    - DynamoDB Table
# ==========================================================================

# --------------------------------------------------------------------------
#  Store Path for Terraform State
# --------------------------------------------------------------------------
terraform {
  backend "s3" {
    region         = "ap-southeast-1"
    bucket         = "creator-terraform-remote-state"
    dynamodb_table = "creator-terraform-state-lock"
    key            = "resources/eks/terraform.tfstate"
    encrypt        = true
  }
}
